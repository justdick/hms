<?php

use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\VitalsAlert;
use App\Models\VitalsSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

it('creates alerts for schedules at due time', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'is_active' => true,
        'next_due_at' => now()->subMinutes(5),
        'created_by' => $user->id,
    ]);

    artisan('vitals:check-due')->assertSuccessful();

    $alert = VitalsAlert::where('vitals_schedule_id', $schedule->id)->first();

    expect($alert)->not->toBeNull()
        ->and($alert->status)->toBe('due');
});

it('updates alert status to overdue after 15 minutes', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'is_active' => true,
        'next_due_at' => now()->subMinutes(20),
        'created_by' => $user->id,
    ]);

    artisan('vitals:check-due')->assertSuccessful();

    $alert = VitalsAlert::where('vitals_schedule_id', $schedule->id)->first();

    expect($alert)->not->toBeNull()
        ->and($alert->status)->toBe('overdue');
});

it('handles multiple schedules correctly', function () {
    $user = User::factory()->create();
    $admission1 = PatientAdmission::factory()->create();
    $admission2 = PatientAdmission::factory()->create();
    $admission3 = PatientAdmission::factory()->create();

    $dueSchedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission1->id,
        'is_active' => true,
        'next_due_at' => now()->subMinutes(5),
        'created_by' => $user->id,
    ]);

    $overdueSchedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission2->id,
        'is_active' => true,
        'next_due_at' => now()->subMinutes(20),
        'created_by' => $user->id,
    ]);

    $futureSchedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission3->id,
        'is_active' => true,
        'next_due_at' => now()->addHours(2),
        'created_by' => $user->id,
    ]);

    artisan('vitals:check-due')->assertSuccessful();

    $dueAlert = VitalsAlert::where('vitals_schedule_id', $dueSchedule->id)->first();
    $overdueAlert = VitalsAlert::where('vitals_schedule_id', $overdueSchedule->id)->first();
    $futureAlert = VitalsAlert::where('vitals_schedule_id', $futureSchedule->id)->first();

    expect($dueAlert)->not->toBeNull()
        ->and($dueAlert->status)->toBe('due')
        ->and($overdueAlert)->not->toBeNull()
        ->and($overdueAlert->status)->toBe('overdue')
        ->and($futureAlert)->toBeNull();
});

it('skips inactive schedules', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();

    $inactiveSchedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'is_active' => false,
        'next_due_at' => now()->subMinutes(5),
        'created_by' => $user->id,
    ]);

    artisan('vitals:check-due')->assertSuccessful();

    $alert = VitalsAlert::where('vitals_schedule_id', $inactiveSchedule->id)->first();

    expect($alert)->toBeNull();
});

it('does not create duplicate alerts for same schedule', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'is_active' => true,
        'next_due_at' => now()->subMinutes(5),
        'created_by' => $user->id,
    ]);

    artisan('vitals:check-due')->assertSuccessful();
    artisan('vitals:check-due')->assertSuccessful();

    $alertCount = VitalsAlert::where('vitals_schedule_id', $schedule->id)->count();

    expect($alertCount)->toBe(1);
});

it('updates existing alert from due to overdue', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'is_active' => true,
        'next_due_at' => now()->subMinutes(10),
        'created_by' => $user->id,
    ]);

    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
        'due_at' => $schedule->next_due_at,
        'status' => 'due',
    ]);

    // Update schedule to be overdue
    $schedule->update(['next_due_at' => now()->subMinutes(20)]);

    artisan('vitals:check-due')->assertSuccessful();

    $alert->refresh();

    expect($alert->status)->toBe('overdue');
});

it('skips schedules with null next_due_at', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'is_active' => true,
        'next_due_at' => null,
        'created_by' => $user->id,
    ]);

    artisan('vitals:check-due')->assertSuccessful();

    $alert = VitalsAlert::where('vitals_schedule_id', $schedule->id)->first();

    expect($alert)->toBeNull();
});
