<?php

use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\VitalSign;
use App\Models\VitalsSchedule;
use App\Services\VitalsScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new VitalsScheduleService;
});

it('creates schedule with valid data', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create(['status' => 'admitted']);

    $schedule = $this->service->createSchedule($admission, 240, $user);

    expect($schedule)->toBeInstanceOf(VitalsSchedule::class)
        ->and($schedule->patient_admission_id)->toBe($admission->id)
        ->and($schedule->interval_minutes)->toBe(240)
        ->and($schedule->is_active)->toBeTrue()
        ->and($schedule->created_by)->toBe($user->id)
        ->and($schedule->next_due_at)->not->toBeNull();
});

it('throws exception when interval is below minimum', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create(['status' => 'admitted']);

    $this->service->createSchedule($admission, 10, $user);
})->throws(\InvalidArgumentException::class, 'Interval must be between 15 and 1440 minutes');

it('throws exception when interval exceeds maximum', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create(['status' => 'admitted']);

    $this->service->createSchedule($admission, 1500, $user);
})->throws(\InvalidArgumentException::class, 'Interval must be between 15 and 1440 minutes');

it('throws exception when patient is not admitted', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create(['status' => 'discharged']);

    $this->service->createSchedule($admission, 240, $user);
})->throws(\InvalidArgumentException::class, 'Cannot create schedule for non-admitted patient');

it('disables existing active schedules when creating new one', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create(['status' => 'admitted']);

    $oldSchedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    $newSchedule = $this->service->createSchedule($admission, 240, $user);

    $oldSchedule->refresh();

    expect($oldSchedule->is_active)->toBeFalse()
        ->and($newSchedule->is_active)->toBeTrue();
});

it('updates schedule interval correctly', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 120,
        'created_by' => $user->id,
    ]);

    $updatedSchedule = $this->service->updateSchedule($schedule, 240);

    expect($updatedSchedule->interval_minutes)->toBe(240);
});

it('throws exception when updating with invalid interval', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 120,
        'created_by' => $user->id,
    ]);

    $this->service->updateSchedule($schedule, 5);
})->throws(\InvalidArgumentException::class, 'Interval must be between 15 and 1440 minutes');

it('recalculates next due time when updating schedule', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();
    $lastRecorded = Carbon::parse('2024-01-01 10:00:00');
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 120,
        'last_recorded_at' => $lastRecorded,
        'next_due_at' => $lastRecorded->copy()->addMinutes(120),
        'created_by' => $user->id,
    ]);

    $updatedSchedule = $this->service->updateSchedule($schedule, 240);

    expect($updatedSchedule->next_due_at->toDateTimeString())->toBe('2024-01-01 14:00:00');
});

it('disables schedule and sets is_active to false', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    $this->service->disableSchedule($schedule);

    $schedule->refresh();

    expect($schedule->is_active)->toBeFalse();
});

it('dismisses pending alerts when disabling schedule', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    $alert = $schedule->alerts()->create([
        'patient_admission_id' => $admission->id,
        'due_at' => now(),
        'status' => 'due',
    ]);

    $this->service->disableSchedule($schedule);

    $alert->refresh();

    expect($alert->status)->toBe('dismissed');
});

it('records vitals completed and updates schedule', function () {
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

    $this->service->recordVitalsCompleted($schedule, $vitalSign);

    $schedule->refresh();

    expect($schedule->last_recorded_at->toDateTimeString())->toBe('2024-01-01 14:00:00')
        ->and($schedule->next_due_at->toDateTimeString())->toBe('2024-01-01 18:00:00');
});

it('marks alerts as completed when recording vitals', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 240,
        'created_by' => $user->id,
    ]);

    $alert = $schedule->alerts()->create([
        'patient_admission_id' => $admission->id,
        'due_at' => now(),
        'status' => 'overdue',
    ]);

    $vitalSign = VitalSign::factory()->create([
        'patient_admission_id' => $admission->id,
        'recorded_at' => now(),
        'recorded_by' => $user->id,
    ]);

    $this->service->recordVitalsCompleted($schedule, $vitalSign);

    $alert->refresh();

    expect($alert->status)->toBe('completed');
});

it('returns correct schedule status array', function () {
    $user = User::factory()->create();
    $admission = PatientAdmission::factory()->create();
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 240,
        'next_due_at' => now()->addHours(2),
        'is_active' => true,
        'created_by' => $user->id,
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
        ->and($status['status'])->toBe('upcoming')
        ->and($status['interval_minutes'])->toBe(240)
        ->and($status['is_active'])->toBeTrue();
});
