<?php

use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\VitalsAlert;
use App\Models\VitalsSchedule;
use App\Models\Ward;
use App\Services\VitalsAlertService;

beforeEach(function () {
    $this->service = new VitalsAlertService;
});

it('finds schedules that are due', function () {
    VitalsSchedule::factory()->create([
        'is_active' => true,
        'next_due_at' => now()->subMinutes(5),
    ]);

    VitalsSchedule::factory()->create([
        'is_active' => true,
        'next_due_at' => now()->addMinutes(30),
    ]);

    $dueSchedules = $this->service->checkDueAlerts();

    expect($dueSchedules)->toHaveCount(1);
});

it('does not return inactive schedules', function () {
    VitalsSchedule::factory()->create([
        'is_active' => false,
        'next_due_at' => now()->subMinutes(5),
    ]);

    $dueSchedules = $this->service->checkDueAlerts();

    expect($dueSchedules)->toHaveCount(0);
});

it('finds alerts past grace period', function () {
    VitalsAlert::factory()->create([
        'status' => 'due',
        'due_at' => now()->subMinutes(20),
    ]);

    VitalsAlert::factory()->create([
        'status' => 'due',
        'due_at' => now()->subMinutes(5),
    ]);

    $overdueAlerts = $this->service->checkOverdueAlerts();

    expect($overdueAlerts)->toHaveCount(1);
});

it('creates alert with correct initial status for pending', function () {
    $schedule = VitalsSchedule::factory()->create([
        'next_due_at' => now()->addMinutes(30),
    ]);

    $alert = $this->service->createAlert($schedule);

    expect($alert->status)->toBe('pending')
        ->and($alert->vitals_schedule_id)->toBe($schedule->id)
        ->and($alert->patient_admission_id)->toBe($schedule->patient_admission_id);
});

it('creates alert with due status when at due time', function () {
    $schedule = VitalsSchedule::factory()->create([
        'next_due_at' => now()->subMinutes(5),
    ]);

    $alert = $this->service->createAlert($schedule);

    expect($alert->status)->toBe('due');
});

it('creates alert with overdue status when past grace period', function () {
    $schedule = VitalsSchedule::factory()->create([
        'next_due_at' => now()->subMinutes(20),
    ]);

    $alert = $this->service->createAlert($schedule);

    expect($alert->status)->toBe('overdue');
});

it('returns existing alert instead of creating duplicate', function () {
    $schedule = VitalsSchedule::factory()->create([
        'next_due_at' => now()->addMinutes(30),
    ]);

    $existingAlert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'status' => 'pending',
    ]);

    $alert = $this->service->createAlert($schedule);

    expect($alert->id)->toBe($existingAlert->id)
        ->and(VitalsAlert::where('vitals_schedule_id', $schedule->id)->count())->toBe(1);
});

it('updates alert status correctly', function () {
    $alert = VitalsAlert::factory()->create(['status' => 'pending']);

    $this->service->updateAlertStatus($alert, 'due');

    expect($alert->fresh()->status)->toBe('due');
});

it('throws exception for invalid status', function () {
    $alert = VitalsAlert::factory()->create(['status' => 'pending']);

    $this->service->updateAlertStatus($alert, 'invalid_status');
})->throws(\InvalidArgumentException::class);

it('gets active alerts for ward', function () {
    $ward = Ward::factory()->create();
    $admission1 = PatientAdmission::factory()->create([
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);
    $admission2 = PatientAdmission::factory()->create([
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);
    $otherWardAdmission = PatientAdmission::factory()->create([
        'status' => 'admitted',
    ]);

    VitalsAlert::factory()->create([
        'patient_admission_id' => $admission1->id,
        'status' => 'due',
    ]);
    VitalsAlert::factory()->create([
        'patient_admission_id' => $admission2->id,
        'status' => 'overdue',
    ]);
    VitalsAlert::factory()->create([
        'patient_admission_id' => $otherWardAdmission->id,
        'status' => 'due',
    ]);

    $alerts = $this->service->getActiveAlertsForWard($ward);

    expect($alerts)->toHaveCount(2);
});

it('orders alerts by urgency', function () {
    $ward = Ward::factory()->create();
    $admission1 = PatientAdmission::factory()->create([
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);
    $admission2 = PatientAdmission::factory()->create([
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);
    $admission3 = PatientAdmission::factory()->create([
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    VitalsAlert::factory()->create([
        'patient_admission_id' => $admission1->id,
        'status' => 'pending',
    ]);
    VitalsAlert::factory()->create([
        'patient_admission_id' => $admission2->id,
        'status' => 'overdue',
    ]);
    VitalsAlert::factory()->create([
        'patient_admission_id' => $admission3->id,
        'status' => 'due',
    ]);

    $alerts = $this->service->getActiveAlertsForWard($ward);

    expect($alerts->first()->status)->toBe('overdue')
        ->and($alerts->get(1)->status)->toBe('due')
        ->and($alerts->last()->status)->toBe('pending');
});

it('acknowledges alert', function () {
    $user = User::factory()->create();
    $alert = VitalsAlert::factory()->create([
        'acknowledged_at' => null,
        'acknowledged_by' => null,
    ]);

    $this->service->acknowledgeAlert($alert, $user);

    $alert->refresh();

    expect($alert->acknowledged_at)->not->toBeNull()
        ->and($alert->acknowledged_by)->toBe($user->id);
});

it('dismisses alert', function () {
    $user = User::factory()->create();
    $alert = VitalsAlert::factory()->create(['status' => 'due']);

    $this->service->dismissAlert($alert, $user);

    $alert->refresh();

    expect($alert->status)->toBe('dismissed')
        ->and($alert->acknowledged_at)->not->toBeNull()
        ->and($alert->acknowledged_by)->toBe($user->id);
});

it('gets active alerts for user', function () {
    $user = User::factory()->create();

    $admission1 = PatientAdmission::factory()->create(['status' => 'admitted']);
    $admission2 = PatientAdmission::factory()->create(['status' => 'admitted']);
    $dischargedAdmission = PatientAdmission::factory()->create(['status' => 'discharged']);

    VitalsAlert::factory()->create([
        'patient_admission_id' => $admission1->id,
        'status' => 'due',
    ]);
    VitalsAlert::factory()->create([
        'patient_admission_id' => $admission2->id,
        'status' => 'overdue',
    ]);
    VitalsAlert::factory()->create([
        'patient_admission_id' => $dischargedAdmission->id,
        'status' => 'due',
    ]);

    $alerts = $this->service->getActiveAlertsForUser($user);

    expect($alerts)->toHaveCount(2);
});
