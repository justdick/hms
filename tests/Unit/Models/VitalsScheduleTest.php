<?php

use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\VitalSign;
use App\Models\VitalsSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calculates next due time with 1 hour interval', function () {
    $schedule = VitalsSchedule::factory()->make(['interval_minutes' => 60]);
    $fromTime = Carbon::parse('2024-01-01 10:00:00');

    $nextDueTime = $schedule->calculateNextDueTime($fromTime);

    expect($nextDueTime->toDateTimeString())->toBe('2024-01-01 11:00:00');
});

it('calculates next due time with 4 hour interval', function () {
    $schedule = VitalsSchedule::factory()->make(['interval_minutes' => 240]);
    $fromTime = Carbon::parse('2024-01-01 10:00:00');

    $nextDueTime = $schedule->calculateNextDueTime($fromTime);

    expect($nextDueTime->toDateTimeString())->toBe('2024-01-01 14:00:00');
});

it('calculates next due time with 12 hour interval', function () {
    $schedule = VitalsSchedule::factory()->make(['interval_minutes' => 720]);
    $fromTime = Carbon::parse('2024-01-01 10:00:00');

    $nextDueTime = $schedule->calculateNextDueTime($fromTime);

    expect($nextDueTime->toDateTimeString())->toBe('2024-01-01 22:00:00');
});

it('returns upcoming status when next due time is in future', function () {
    $schedule = VitalsSchedule::factory()->make([
        'next_due_at' => now()->addHours(2),
    ]);

    expect($schedule->getCurrentStatus())->toBe('upcoming');
});

it('returns due status when current time is at due time', function () {
    $schedule = VitalsSchedule::factory()->make([
        'next_due_at' => now(),
    ]);

    expect($schedule->getCurrentStatus())->toBe('due');
});

it('returns due status when current time is within grace period', function () {
    $schedule = VitalsSchedule::factory()->make([
        'next_due_at' => now()->subMinutes(10),
    ]);

    expect($schedule->getCurrentStatus())->toBe('due');
});

it('returns overdue status when past 15 minute grace period', function () {
    $schedule = VitalsSchedule::factory()->make([
        'next_due_at' => now()->subMinutes(20),
    ]);

    expect($schedule->getCurrentStatus())->toBe('overdue');
});

it('returns upcoming status when next_due_at is null', function () {
    $schedule = VitalsSchedule::factory()->make([
        'next_due_at' => null,
    ]);

    expect($schedule->getCurrentStatus())->toBe('upcoming');
});

it('calculates time until due correctly', function () {
    $schedule = VitalsSchedule::factory()->make([
        'next_due_at' => now()->addMinutes(30),
    ]);

    $timeUntilDue = $schedule->getTimeUntilDue();

    expect($timeUntilDue)->toBeGreaterThanOrEqual(29)
        ->and($timeUntilDue)->toBeLessThanOrEqual(30);
});

it('returns 0 for time until due when already due', function () {
    $schedule = VitalsSchedule::factory()->make([
        'next_due_at' => now()->subMinutes(5),
    ]);

    expect($schedule->getTimeUntilDue())->toBe(0);
});

it('returns null for time until due when next_due_at is null', function () {
    $schedule = VitalsSchedule::factory()->make([
        'next_due_at' => null,
    ]);

    expect($schedule->getTimeUntilDue())->toBeNull();
});

it('calculates time overdue correctly after grace period', function () {
    $schedule = VitalsSchedule::factory()->make([
        'next_due_at' => now()->subMinutes(25),
    ]);

    $timeOverdue = $schedule->getTimeOverdue();

    expect($timeOverdue)->toBeGreaterThanOrEqual(9)
        ->and($timeOverdue)->toBeLessThanOrEqual(10);
});

it('returns 0 for time overdue when within grace period', function () {
    $schedule = VitalsSchedule::factory()->make([
        'next_due_at' => now()->subMinutes(10),
    ]);

    expect($schedule->getTimeOverdue())->toBe(0);
});

it('returns 0 for time overdue when not yet due', function () {
    $schedule = VitalsSchedule::factory()->make([
        'next_due_at' => now()->addMinutes(30),
    ]);

    expect($schedule->getTimeOverdue())->toBe(0);
});

it('returns null for time overdue when next_due_at is null', function () {
    $schedule = VitalsSchedule::factory()->make([
        'next_due_at' => null,
    ]);

    expect($schedule->getTimeOverdue())->toBeNull();
});

it('marks schedule as completed and updates next due time', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 240,
        'next_due_at' => now()->subMinutes(10),
        'last_recorded_at' => null,
        'created_by' => $user->id,
    ]);

    $recordedAt = Carbon::parse('2024-01-01 14:00:00');
    $vitalSign = VitalSign::factory()->create([
        'patient_admission_id' => $admission->id,
        'recorded_at' => $recordedAt,
        'recorded_by' => $user->id,
    ]);

    $schedule->markAsCompleted($vitalSign);

    $schedule->refresh();

    expect($schedule->last_recorded_at->toDateTimeString())->toBe('2024-01-01 14:00:00')
        ->and($schedule->next_due_at->toDateTimeString())->toBe('2024-01-01 18:00:00');
});

it('marks pending alerts as completed when schedule is completed', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 240,
        'next_due_at' => now()->subMinutes(10),
        'created_by' => $user->id,
    ]);

    $alert = $schedule->alerts()->create([
        'patient_admission_id' => $admission->id,
        'due_at' => now()->subMinutes(10),
        'status' => 'due',
    ]);

    $vitalSign = VitalSign::factory()->create([
        'patient_admission_id' => $admission->id,
        'recorded_at' => now(),
        'recorded_by' => $user->id,
    ]);

    $schedule->markAsCompleted($vitalSign);

    $alert->refresh();

    expect($alert->status)->toBe('completed');
});
