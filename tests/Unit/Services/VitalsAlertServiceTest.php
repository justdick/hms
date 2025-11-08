<?php

use App\Models\Bed;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\VitalsAlert;
use App\Models\VitalsSchedule;
use App\Models\Ward;
use App\Services\VitalsAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new VitalsAlertService;
});

it('finds schedules at due time', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();

    $dueSchedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'is_active' => true,
        'next_due_at' => now()->subMinutes(5),
        'created_by' => $user->id,
    ]);

    $futureSchedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'is_active' => true,
        'next_due_at' => now()->addHours(2),
        'created_by' => $user->id,
    ]);

    $dueSchedules = $this->service->checkDueAlerts();

    expect($dueSchedules)->toHaveCount(1)
        ->and($dueSchedules->first()->id)->toBe($dueSchedule->id);
});

it('finds alerts past grace period', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'created_by' => $user->id,
    ]);

    $overdueAlert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
        'due_at' => now()->subMinutes(20),
        'status' => 'due',
    ]);

    $recentAlert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
        'due_at' => now()->subMinutes(5),
        'status' => 'due',
    ]);

    $overdueAlerts = $this->service->checkOverdueAlerts();

    expect($overdueAlerts)->toHaveCount(1)
        ->and($overdueAlerts->first()->id)->toBe($overdueAlert->id);
});

it('creates alert with correct status for due schedule', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'next_due_at' => now()->subMinutes(5),
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    $alert = $this->service->createAlert($schedule);

    expect($alert)->toBeInstanceOf(VitalsAlert::class)
        ->and($alert->vitals_schedule_id)->toBe($schedule->id)
        ->and($alert->patient_admission_id)->toBe($admission->id)
        ->and($alert->status)->toBe('due');
});

it('creates alert with overdue status when past grace period', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'next_due_at' => now()->subMinutes(20),
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    $alert = $this->service->createAlert($schedule);

    expect($alert->status)->toBe('overdue');
});

it('returns existing alert instead of creating duplicate', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'next_due_at' => now()->subMinutes(5),
        'created_by' => $user->id,
    ]);

    $existingAlert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
        'due_at' => $schedule->next_due_at,
        'status' => 'due',
    ]);

    $alert = $this->service->createAlert($schedule);

    expect($alert->id)->toBe($existingAlert->id)
        ->and(VitalsAlert::where('vitals_schedule_id', $schedule->id)->count())->toBe(1);
});

it('updates alert status correctly', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'created_by' => $user->id,
    ]);

    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
        'status' => 'pending',
    ]);

    $this->service->updateAlertStatus($alert, 'due');

    $alert->refresh();

    expect($alert->status)->toBe('due');
});

it('throws exception for invalid status', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'created_by' => $user->id,
    ]);

    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
        'status' => 'pending',
    ]);

    $this->service->updateAlertStatus($alert, 'invalid_status');
})->throws(\InvalidArgumentException::class);

it('filters active alerts by ward', function () {
    $user = User::factory()->create();
    $ward1 = Ward::factory()->create();
    $ward2 = Ward::factory()->create();

    $bed1 = Bed::create([
        'ward_id' => $ward1->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);

    $bed2 = Bed::create([
        'ward_id' => $ward2->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);

    $patient1 = Patient::factory()->create();
    $admission1 = PatientAdmission::factory()->create([
        'patient_id' => $patient1->id,
        'ward_id' => $ward1->id,
        'bed_id' => $bed1->id,
        'status' => 'admitted',
    ]);

    $patient2 = Patient::factory()->create();
    $admission2 = PatientAdmission::factory()->create([
        'patient_id' => $patient2->id,
        'ward_id' => $ward2->id,
        'bed_id' => $bed2->id,
        'status' => 'admitted',
    ]);

    $schedule1 = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission1->id,
        'created_by' => $user->id,
    ]);

    $schedule2 = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission2->id,
        'created_by' => $user->id,
    ]);

    $alert1 = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule1->id,
        'patient_admission_id' => $admission1->id,
        'status' => 'due',
    ]);

    $alert2 = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule2->id,
        'patient_admission_id' => $admission2->id,
        'status' => 'due',
    ]);

    $alerts = $this->service->getActiveAlertsForWard($ward1);

    expect($alerts)->toHaveCount(1)
        ->and($alerts->first()->id)->toBe($alert1->id);
});

it('sorts alerts by urgency with overdue first', function () {
    $user = User::factory()->create();
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);

    $patient = Patient::factory()->create();
    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'ward_id' => $ward->id,
        'bed_id' => $bed->id,
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'created_by' => $user->id,
    ]);

    $pendingAlert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
        'status' => 'pending',
        'due_at' => now()->addHours(1),
    ]);

    $dueAlert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
        'status' => 'due',
        'due_at' => now()->subMinutes(5),
    ]);

    $overdueAlert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
        'status' => 'overdue',
        'due_at' => now()->subMinutes(20),
    ]);

    $alerts = $this->service->getActiveAlertsForWard($ward);

    expect($alerts)->toHaveCount(3)
        ->and($alerts->first()->id)->toBe($overdueAlert->id)
        ->and($alerts->get(1)->id)->toBe($dueAlert->id)
        ->and($alerts->last()->id)->toBe($pendingAlert->id);
});

it('acknowledges alert with user', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'created_by' => $user->id,
    ]);

    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
        'status' => 'due',
    ]);

    $acknowledgingUser = User::factory()->create();
    $this->service->acknowledgeAlert($alert, $acknowledgingUser);

    $alert->refresh();

    expect($alert->acknowledged_by)->toBe($acknowledgingUser->id)
        ->and($alert->acknowledged_at)->not->toBeNull();
});

it('dismisses alert and acknowledges it', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'created_by' => $user->id,
    ]);

    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
        'status' => 'due',
    ]);

    $dismissingUser = User::factory()->create();
    $this->service->dismissAlert($alert, $dismissingUser);

    $alert->refresh();

    expect($alert->status)->toBe('dismissed')
        ->and($alert->acknowledged_by)->toBe($dismissingUser->id)
        ->and($alert->acknowledged_at)->not->toBeNull();
});
