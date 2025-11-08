<?php

use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\VitalsSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('discharging patient disables active vitals schedule', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create(['status' => 'admitted']);

    // Create an active vitals schedule
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 120,
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    // Discharge the patient
    $admission->markAsDischarged($user, 'Patient recovered');

    // Refresh schedule
    $schedule->refresh();

    // Assert schedule was disabled
    expect($schedule->is_active)->toBeFalse();
});

test('discharging patient dismisses pending alerts', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create(['status' => 'admitted']);

    // Create an active vitals schedule
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 120,
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    // Create alerts in different states
    $pendingAlert = $schedule->alerts()->create([
        'patient_admission_id' => $admission->id,
        'due_at' => now()->addMinutes(30),
        'status' => 'pending',
    ]);

    $dueAlert = $schedule->alerts()->create([
        'patient_admission_id' => $admission->id,
        'due_at' => now()->subMinutes(5),
        'status' => 'due',
    ]);

    $overdueAlert = $schedule->alerts()->create([
        'patient_admission_id' => $admission->id,
        'due_at' => now()->subMinutes(20),
        'status' => 'overdue',
    ]);

    $completedAlert = $schedule->alerts()->create([
        'patient_admission_id' => $admission->id,
        'due_at' => now()->subHours(2),
        'status' => 'completed',
    ]);

    // Discharge the patient
    $admission->markAsDischarged($user, 'Patient recovered');

    // Refresh alerts
    $pendingAlert->refresh();
    $dueAlert->refresh();
    $overdueAlert->refresh();
    $completedAlert->refresh();

    // Assert pending, due, and overdue alerts were dismissed
    expect($pendingAlert->status)->toBe('dismissed')
        ->and($dueAlert->status)->toBe('dismissed')
        ->and($overdueAlert->status)->toBe('dismissed');

    // Assert completed alert was not changed
    expect($completedAlert->status)->toBe('completed');
});

test('discharging patient without schedule does not throw error', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create(['status' => 'admitted']);

    // Discharge the patient without a schedule
    $admission->markAsDischarged($user, 'Patient recovered');

    // Assert discharge was successful
    expect($admission->status)->toBe('discharged')
        ->and($admission->discharged_at)->not->toBeNull()
        ->and($admission->discharged_by_id)->toBe($user->id);
});

test('patient admission has vitals schedule relationships', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create(['status' => 'admitted']);

    // Create schedules
    $activeSchedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    $inactiveSchedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'is_active' => false,
        'created_by' => $user->id,
    ]);

    // Test vitalsSchedule relationship (returns first schedule)
    $schedule = $admission->vitalsSchedule;
    expect($schedule)->not->toBeNull()
        ->and($schedule->id)->toBeIn([$activeSchedule->id, $inactiveSchedule->id]);

    // Test activeVitalsSchedule relationship (returns only active)
    $activeScheduleFromRelation = $admission->activeVitalsSchedule;
    expect($activeScheduleFromRelation)->not->toBeNull()
        ->and($activeScheduleFromRelation->id)->toBe($activeSchedule->id)
        ->and($activeScheduleFromRelation->is_active)->toBeTrue();
});

test('patient admission without active schedule returns null', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create(['status' => 'admitted']);

    // Create only inactive schedule
    VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'is_active' => false,
        'created_by' => $user->id,
    ]);

    // Test activeVitalsSchedule relationship
    $activeSchedule = $admission->activeVitalsSchedule;
    expect($activeSchedule)->toBeNull();
});
