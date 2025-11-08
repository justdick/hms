<?php

use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\VitalsAlert;
use App\Models\VitalsSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('marks alert as due', function () {
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

    $alert->markAsDue();

    expect($alert->status)->toBe('due');
});

it('marks alert as overdue', function () {
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

    $alert->markAsOverdue();

    expect($alert->status)->toBe('overdue');
});

it('marks alert as completed', function () {
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

    $alert->markAsCompleted();

    expect($alert->status)->toBe('completed');
});

it('acknowledges alert with user and timestamp', function () {
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
        'acknowledged_at' => null,
        'acknowledged_by' => null,
    ]);

    $acknowledgingUser = User::factory()->create();
    $alert->acknowledge($acknowledgingUser);

    expect($alert->acknowledged_by)->toBe($acknowledgingUser->id)
        ->and($alert->acknowledged_at)->not->toBeNull()
        ->and($alert->acknowledged_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('loads vitals schedule relationship', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'created_by' => $user->id,
    ]);

    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
    ]);

    $loadedAlert = VitalsAlert::with('vitalsSchedule')->find($alert->id);

    expect($loadedAlert->vitalsSchedule)->not->toBeNull()
        ->and($loadedAlert->vitalsSchedule->id)->toBe($schedule->id);
});

it('loads patient admission relationship', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'created_by' => $user->id,
    ]);

    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
    ]);

    $loadedAlert = VitalsAlert::with('patientAdmission')->find($alert->id);

    expect($loadedAlert->patientAdmission)->not->toBeNull()
        ->and($loadedAlert->patientAdmission->id)->toBe($admission->id);
});

it('loads acknowledged by user relationship', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'created_by' => $user->id,
    ]);

    $acknowledgingUser = User::factory()->create();
    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
        'acknowledged_by' => $acknowledgingUser->id,
        'acknowledged_at' => now(),
    ]);

    $loadedAlert = VitalsAlert::with('acknowledgedBy')->find($alert->id);

    expect($loadedAlert->acknowledgedBy)->not->toBeNull()
        ->and($loadedAlert->acknowledgedBy->id)->toBe($acknowledgingUser->id);
});
