<?php

use App\Models\Bed;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\VitalsAlert;
use App\Models\VitalsSchedule;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('disables vitals schedule when patient is discharged', function () {
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
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    $admission->markAsDischarged($user, 'Patient recovered');

    $schedule->refresh();

    expect($schedule->is_active)->toBeFalse();
});

it('dismisses pending alerts when patient is discharged', function () {
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
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'is_active' => true,
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

    $admission->markAsDischarged($user, 'Patient recovered');

    $pendingAlert->refresh();
    $dueAlert->refresh();
    $overdueAlert->refresh();

    expect($pendingAlert->status)->toBe('dismissed')
        ->and($dueAlert->status)->toBe('dismissed')
        ->and($overdueAlert->status)->toBe('dismissed');
});

it('does not create new alerts after discharge', function () {
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
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'is_active' => true,
        'next_due_at' => now()->subMinutes(5),
        'created_by' => $user->id,
    ]);

    $admission->markAsDischarged($user, 'Patient recovered');

    // Run the command that checks for due vitals
    \Illuminate\Support\Facades\Artisan::call('vitals:check-due');

    $alerts = VitalsAlert::where('vitals_schedule_id', $schedule->id)
        ->whereIn('status', ['pending', 'due', 'overdue'])
        ->get();

    expect($alerts)->toHaveCount(0);
});

it('handles discharge when no vitals schedule exists', function () {
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
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    // No schedule created

    $admission->markAsDischarged($user, 'Patient recovered');

    expect($admission->status)->toBe('discharged');
});

it('does not affect completed alerts on discharge', function () {
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
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    $completedAlert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
        'status' => 'completed',
        'due_at' => now()->subHours(2),
    ]);

    $admission->markAsDischarged($user, 'Patient recovered');

    $completedAlert->refresh();

    expect($completedAlert->status)->toBe('completed');
});

it('marks bed as available when patient with vitals schedule is discharged', function () {
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
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    $admission->markAsDischarged($user, 'Patient recovered');

    $bed->refresh();

    expect($bed->status)->toBe('available');
});
