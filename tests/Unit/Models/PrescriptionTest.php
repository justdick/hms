<?php

use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('discontinues prescription with user and reason', function () {
    $user = User::factory()->create();
    $prescription = Prescription::withoutEvents(function () {
        return Prescription::factory()->create([
            'discontinued_at' => null,
            'discontinued_by_id' => null,
            'discontinuation_reason' => null,
        ]);
    });

    $prescription->discontinue($user, 'Patient switched to alternative medication');

    expect($prescription->discontinued_at)->not->toBeNull()
        ->and($prescription->discontinued_at)->toBeInstanceOf(\Carbon\Carbon::class)
        ->and($prescription->discontinued_by_id)->toBe($user->id)
        ->and($prescription->discontinuation_reason)->toBe('Patient switched to alternative medication');
});

it('discontinues prescription without reason', function () {
    $user = User::factory()->create();
    $prescription = Prescription::withoutEvents(function () {
        return Prescription::factory()->create([
            'discontinued_at' => null,
            'discontinued_by_id' => null,
            'discontinuation_reason' => null,
        ]);
    });

    $prescription->discontinue($user);

    expect($prescription->discontinued_at)->not->toBeNull()
        ->and($prescription->discontinued_by_id)->toBe($user->id)
        ->and($prescription->discontinuation_reason)->toBeNull();
});

it('checks if prescription is discontinued', function () {
    $user = User::factory()->create();
    $prescription = Prescription::withoutEvents(function () {
        return Prescription::factory()->create([
            'discontinued_at' => null,
        ]);
    });

    expect($prescription->isDiscontinued())->toBeFalse();

    $prescription->discontinue($user, 'Test reason');

    expect($prescription->isDiscontinued())->toBeTrue();
});

it('checks if prescription can be discontinued', function () {
    $user = User::factory()->create();
    $prescription = Prescription::withoutEvents(function () {
        return Prescription::factory()->create([
            'discontinued_at' => null,
        ]);
    });

    expect($prescription->canBeDiscontinued())->toBeTrue();

    $prescription->discontinue($user, 'Test reason');

    expect($prescription->canBeDiscontinued())->toBeFalse();
});

it('loads discontinued by user relationship', function () {
    $user = User::factory()->create();
    $prescription = Prescription::withoutEvents(function () {
        return Prescription::factory()->create([
            'discontinued_at' => null,
        ]);
    });

    $prescription->discontinue($user, 'Test reason');

    $loadedPrescription = Prescription::with('discontinuedBy')->find($prescription->id);

    expect($loadedPrescription->discontinuedBy)->not->toBeNull()
        ->and($loadedPrescription->discontinuedBy->id)->toBe($user->id);
});

it('filters active prescriptions using scope', function () {
    $user = User::factory()->create();

    $activePrescription = Prescription::withoutEvents(function () {
        return Prescription::factory()->create([
            'discontinued_at' => null,
        ]);
    });

    $discontinuedPrescription = Prescription::withoutEvents(function () {
        return Prescription::factory()->create([
            'discontinued_at' => null,
        ]);
    });
    $discontinuedPrescription->discontinue($user, 'Test reason');

    $activePrescriptions = Prescription::active()->get();

    expect($activePrescriptions)->toHaveCount(1)
        ->and($activePrescriptions->first()->id)->toBe($activePrescription->id);
});

it('checks if prescription has schedule', function () {
    $prescriptionWithSchedule = Prescription::withoutEvents(function () {
        return Prescription::factory()->create([
            'schedule_pattern' => [
                'day_1' => ['10:30', '18:00'],
                'subsequent' => ['06:00', '18:00'],
            ],
        ]);
    });

    $prescriptionWithoutSchedule = Prescription::withoutEvents(function () {
        return Prescription::factory()->create([
            'schedule_pattern' => null,
        ]);
    });

    expect($prescriptionWithSchedule->hasSchedule())->toBeTrue()
        ->and($prescriptionWithoutSchedule->hasSchedule())->toBeFalse();
});

it('checks if prescription is pending schedule', function () {
    $prescriptionPendingSchedule = Prescription::withoutEvents(function () {
        return Prescription::factory()->create([
            'schedule_pattern' => null,
            'frequency' => 'BID',
        ]);
    });

    $prescriptionWithSchedule = Prescription::withoutEvents(function () {
        return Prescription::factory()->create([
            'schedule_pattern' => [
                'day_1' => ['10:30', '18:00'],
                'subsequent' => ['06:00', '18:00'],
            ],
            'frequency' => 'BID',
        ]);
    });

    $prnPrescription = Prescription::withoutEvents(function () {
        return Prescription::factory()->create([
            'schedule_pattern' => null,
            'frequency' => 'PRN',
        ]);
    });

    expect($prescriptionPendingSchedule->isPendingSchedule())->toBeTrue()
        ->and($prescriptionWithSchedule->isPendingSchedule())->toBeFalse()
        ->and($prnPrescription->isPendingSchedule())->toBeFalse();
});

it('casts schedule_pattern as json', function () {
    $schedulePattern = [
        'day_1' => ['10:30', '18:00'],
        'day_2' => ['10:00', '22:00'],
        'subsequent' => ['06:00', '18:00'],
    ];

    $prescription = Prescription::withoutEvents(function () use ($schedulePattern) {
        return Prescription::factory()->create([
            'schedule_pattern' => $schedulePattern,
        ]);
    });

    expect($prescription->schedule_pattern)->toBeArray()
        ->and($prescription->schedule_pattern)->toBe($schedulePattern);

    $loadedPrescription = Prescription::find($prescription->id);

    expect($loadedPrescription->schedule_pattern)->toBeArray()
        ->and($loadedPrescription->schedule_pattern)->toBe($schedulePattern);
});
