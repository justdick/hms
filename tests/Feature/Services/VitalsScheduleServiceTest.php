<?php

use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\VitalSign;
use App\Models\VitalsSchedule;
use App\Services\VitalsScheduleService;

beforeEach(function () {
    $this->service = new VitalsScheduleService;
});

it('creates a schedule with valid data', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create(['status' => 'admitted']);

    $schedule = $this->service->createSchedule($admission, 120, $user);

    expect($schedule)->toBeInstanceOf(VitalsSchedule::class)
        ->and($schedule->patient_admission_id)->toBe($admission->id)
        ->and($schedule->interval_minutes)->toBe(120)
        ->and($schedule->is_active)->toBeTrue()
        ->and($schedule->created_by)->toBe($user->id)
        ->and($schedule->next_due_at)->not->toBeNull();
});

it('throws exception for interval below minimum', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create(['status' => 'admitted']);

    $this->service->createSchedule($admission, 10, $user);
})->throws(\InvalidArgumentException::class, 'Interval must be between 15 and 1440 minutes');

it('throws exception for interval above maximum', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create(['status' => 'admitted']);

    $this->service->createSchedule($admission, 1500, $user);
})->throws(\InvalidArgumentException::class, 'Interval must be between 15 and 1440 minutes');

it('throws exception for non-admitted patient', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create(['status' => 'discharged']);

    $this->service->createSchedule($admission, 120, $user);
})->throws(\InvalidArgumentException::class, 'Cannot create schedule for non-admitted patient');

it('disables existing active schedules when creating new one', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create(['status' => 'admitted']);

    $oldSchedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'is_active' => true,
    ]);

    $newSchedule = $this->service->createSchedule($admission, 120, $user);

    expect($oldSchedule->fresh()->is_active)->toBeFalse()
        ->and($newSchedule->is_active)->toBeTrue();
});

it('updates schedule interval', function () {
    $schedule = VitalsSchedule::factory()->create(['interval_minutes' => 120]);

    $updated = $this->service->updateSchedule($schedule, 240);

    expect($updated->interval_minutes)->toBe(240)
        ->and($updated->next_due_at)->not->toBeNull();
});

it('throws exception when updating with invalid interval', function () {
    $schedule = VitalsSchedule::factory()->create(['interval_minutes' => 120]);

    $this->service->updateSchedule($schedule, 5);
})->throws(\InvalidArgumentException::class);

it('disables schedule and dismisses pending alerts', function () {
    $schedule = VitalsSchedule::factory()->create(['is_active' => true]);
    $alert = \App\Models\VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'status' => 'due',
    ]);

    $this->service->disableSchedule($schedule);

    expect($schedule->fresh()->is_active)->toBeFalse()
        ->and($alert->fresh()->status)->toBe('dismissed');
});

it('calculates next due time correctly for 1 hour interval', function () {
    $schedule = VitalsSchedule::factory()->make(['interval_minutes' => 60]);
    $fromTime = now();

    $nextDue = $this->service->calculateNextDueTime($schedule, $fromTime);

    expect((int) $fromTime->diffInMinutes($nextDue))->toBe(60);
});

it('calculates next due time correctly for 4 hour interval', function () {
    $schedule = VitalsSchedule::factory()->make(['interval_minutes' => 240]);
    $fromTime = now();

    $nextDue = $this->service->calculateNextDueTime($schedule, $fromTime);

    expect((int) $fromTime->diffInMinutes($nextDue))->toBe(240);
});

it('calculates next due time correctly for 12 hour interval', function () {
    $schedule = VitalsSchedule::factory()->make(['interval_minutes' => 720]);
    $fromTime = now();

    $nextDue = $this->service->calculateNextDueTime($schedule, $fromTime);

    expect((int) $fromTime->diffInMinutes($nextDue))->toBe(720);
});

it('records vitals completed and updates schedule', function () {
    $schedule = VitalsSchedule::factory()->create([
        'interval_minutes' => 120,
        'next_due_at' => now()->addHours(2),
    ]);

    $vitalSign = VitalSign::factory()->create([
        'patient_admission_id' => $schedule->patient_admission_id,
        'recorded_at' => now(),
    ]);

    $alert = \App\Models\VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'status' => 'due',
    ]);

    $this->service->recordVitalsCompleted($schedule, $vitalSign);

    $schedule->refresh();

    expect($schedule->last_recorded_at)->toEqual($vitalSign->recorded_at)
        ->and($schedule->next_due_at)->not->toBeNull()
        ->and($alert->fresh()->status)->toBe('completed');
});

it('returns correct schedule status', function () {
    $schedule = VitalsSchedule::factory()->create([
        'interval_minutes' => 120,
        'next_due_at' => now()->addHours(1),
        'last_recorded_at' => now()->subHours(1),
        'is_active' => true,
    ]);

    $status = $this->service->getScheduleStatus($schedule);

    expect($status)->toBeArray()
        ->and($status)->toHaveKeys([
            'status',
            'next_due_at',
            'time_until_due_minutes',
            'time_overdue_minutes',
            'interval_minutes',
            'last_recorded_at',
            'is_active',
        ])
        ->and($status['interval_minutes'])->toBe(120)
        ->and($status['is_active'])->toBeTrue();
});
