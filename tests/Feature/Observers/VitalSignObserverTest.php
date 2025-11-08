<?php

use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\VitalSign;
use App\Models\VitalsSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('recording vitals updates active schedule and marks alerts as completed', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create(['status' => 'admitted']);

    // Create an active vitals schedule
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->subMinutes(10),
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    // Create a due alert
    $alert = $schedule->alerts()->create([
        'patient_admission_id' => $admission->id,
        'due_at' => now()->subMinutes(10),
        'status' => 'due',
    ]);

    // Record vitals using factory
    $vitalSign = VitalSign::factory()->create([
        'patient_id' => $admission->patient_id,
        'patient_admission_id' => $admission->id,
        'recorded_by' => $user->id,
        'recorded_at' => now(),
    ]);

    // Refresh models
    $schedule->refresh();
    $alert->refresh();

    // Assert schedule was updated
    expect($schedule->last_recorded_at)->not->toBeNull()
        ->and($schedule->last_recorded_at->timestamp)->toBe($vitalSign->recorded_at->timestamp)
        ->and($schedule->next_due_at)->not->toBeNull()
        ->and($schedule->next_due_at->greaterThan(now()))->toBeTrue();

    // Assert next due time is calculated correctly (120 minutes from recorded time)
    $expectedNextDue = $vitalSign->recorded_at->copy()->addMinutes(120);
    expect($schedule->next_due_at->timestamp)->toBe($expectedNextDue->timestamp);

    // Assert alert was marked as completed
    expect($alert->status)->toBe('completed');
});

test('recording vitals for admission without schedule does nothing', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create(['status' => 'admitted']);

    // Record vitals without a schedule using factory
    $vitalSign = VitalSign::factory()->create([
        'patient_id' => $admission->patient_id,
        'patient_admission_id' => $admission->id,
        'recorded_by' => $user->id,
        'recorded_at' => now(),
    ]);

    // Should not throw any errors
    expect($vitalSign)->not->toBeNull();
});

test('recording vitals for non-admission patient does nothing', function () {
    $user = User::factory()->create();
    $patient = \App\Models\Patient::factory()->create();

    // Record vitals without admission using factory
    $vitalSign = VitalSign::factory()->create([
        'patient_id' => $patient->id,
        'patient_admission_id' => null,
        'recorded_by' => $user->id,
        'recorded_at' => now(),
    ]);

    // Should not throw any errors
    expect($vitalSign)->not->toBeNull();
});

test('recording vitals marks multiple pending alerts as completed', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create(['status' => 'admitted']);

    // Create an active vitals schedule
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->subMinutes(30),
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    // Create multiple alerts in different states
    $pendingAlert = $schedule->alerts()->create([
        'patient_admission_id' => $admission->id,
        'due_at' => now()->subMinutes(30),
        'status' => 'pending',
    ]);

    $dueAlert = $schedule->alerts()->create([
        'patient_admission_id' => $admission->id,
        'due_at' => now()->subMinutes(20),
        'status' => 'due',
    ]);

    $overdueAlert = $schedule->alerts()->create([
        'patient_admission_id' => $admission->id,
        'due_at' => now()->subMinutes(40),
        'status' => 'overdue',
    ]);

    // Record vitals using factory
    VitalSign::factory()->create([
        'patient_id' => $admission->patient_id,
        'patient_admission_id' => $admission->id,
        'recorded_by' => $user->id,
        'recorded_at' => now(),
    ]);

    // Refresh alerts
    $pendingAlert->refresh();
    $dueAlert->refresh();
    $overdueAlert->refresh();

    // Assert all alerts were marked as completed
    expect($pendingAlert->status)->toBe('completed')
        ->and($dueAlert->status)->toBe('completed')
        ->and($overdueAlert->status)->toBe('completed');
});

test('recording vitals does not affect inactive schedules', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create(['status' => 'admitted']);

    // Create an inactive vitals schedule
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 120,
        'next_due_at' => now()->subMinutes(10),
        'last_recorded_at' => null,
        'is_active' => false,
        'created_by' => $user->id,
    ]);

    $originalNextDue = $schedule->next_due_at->copy();
    $originalLastRecorded = $schedule->last_recorded_at;

    // Record vitals using factory
    VitalSign::factory()->create([
        'patient_id' => $admission->patient_id,
        'patient_admission_id' => $admission->id,
        'recorded_by' => $user->id,
        'recorded_at' => now(),
    ]);

    // Refresh schedule
    $schedule->refresh();

    // Assert schedule was not updated (because it's inactive)
    // The activeVitalsSchedule relationship should return null for inactive schedules
    expect($admission->fresh()->activeVitalsSchedule)->toBeNull()
        ->and($schedule->last_recorded_at)->toBe($originalLastRecorded)
        ->and($schedule->next_due_at->timestamp)->toBe($originalNextDue->timestamp);
});
