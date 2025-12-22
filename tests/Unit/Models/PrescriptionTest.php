<?php

use App\Models\MedicationAdministration;
use App\Models\PatientAdmission;
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

it('calculates expected doses per day based on frequency', function () {
    $frequencies = [
        'OD' => 1,
        'BD' => 2,
        'BID' => 2,
        'TDS' => 3,
        'TID' => 3,
        'QDS' => 4,
        'QID' => 4,
        'Q6H' => 4,
        'Q8H' => 3,
        'Q12H' => 2,
        'Q4H' => 6,
        'Q2H' => 12,
        'PRN' => 0,
    ];

    foreach ($frequencies as $frequency => $expected) {
        $prescription = Prescription::withoutEvents(function () use ($frequency) {
            return Prescription::factory()->create([
                'frequency' => $frequency,
            ]);
        });

        expect($prescription->getExpectedDosesPerDay())->toBe($expected, "Failed for frequency: {$frequency}");
    }
});

it('identifies PRN prescriptions', function () {
    $prnPrescription = Prescription::withoutEvents(function () {
        return Prescription::factory()->create([
            'frequency' => 'PRN',
        ]);
    });

    $prnDescriptive = Prescription::withoutEvents(function () {
        return Prescription::factory()->create([
            'frequency' => 'As needed (PRN)',
        ]);
    });

    $regularPrescription = Prescription::withoutEvents(function () {
        return Prescription::factory()->create([
            'frequency' => 'TDS',
        ]);
    });

    expect($prnPrescription->isPrn())->toBeTrue()
        ->and($prnDescriptive->isPrn())->toBeTrue()
        ->and($regularPrescription->isPrn())->toBeFalse();
});

it('counts today medication administrations', function () {
    $prescription = Prescription::withoutEvents(function () {
        return Prescription::factory()->create();
    });

    $admission = PatientAdmission::factory()->create();

    // Create 2 administrations today
    MedicationAdministration::factory()->count(2)->create([
        'prescription_id' => $prescription->id,
        'patient_admission_id' => $admission->id,
        'administered_at' => now(),
        'status' => 'given',
    ]);

    // Create 1 administration yesterday (should not count)
    MedicationAdministration::factory()->create([
        'prescription_id' => $prescription->id,
        'patient_admission_id' => $admission->id,
        'administered_at' => now()->subDay(),
        'status' => 'given',
    ]);

    // Create 1 held administration today (should not count as given)
    MedicationAdministration::factory()->create([
        'prescription_id' => $prescription->id,
        'patient_admission_id' => $admission->id,
        'administered_at' => now(),
        'status' => 'held',
    ]);

    expect($prescription->getTodayAdministrationCount())->toBe(2);
});
