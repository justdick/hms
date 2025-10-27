<?php

use App\Models\Drug;
use App\Models\MedicationAdministration;
use App\Models\PatientAdmission;
use App\Models\Prescription;
use App\Models\User;
use App\Models\WardRound;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->admission = PatientAdmission::factory()->create([
        'status' => 'admitted',
    ]);
});

it('generates medication administration schedule when prescription is created', function () {
    actingAs($this->user);

    $wardRound = WardRound::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'status' => 'in_progress',
    ]);

    $drug = Drug::factory()->create();

    // Create prescription with TID (three times daily) for 3 days
    $prescription = Prescription::factory()->create([
        'prescribable_type' => WardRound::class,
        'prescribable_id' => $wardRound->id,
        'drug_id' => $drug->id,
        'frequency' => 'TID',
        'duration' => '3 days',
        'dose_quantity' => '500mg',
        'status' => 'prescribed',
    ]);

    // Should create 9 medication administrations (3 times per day * 3 days)
    expect(MedicationAdministration::where('prescription_id', $prescription->id)->count())
        ->toBe(9);

    // Check first day schedule (08:00, 14:00, 20:00)
    $todaySchedule = MedicationAdministration::where('prescription_id', $prescription->id)
        ->whereDate('scheduled_time', now())
        ->orderBy('scheduled_time')
        ->get();

    expect($todaySchedule)->toHaveCount(3)
        ->and($todaySchedule[0]->scheduled_time->format('H:i'))->toBe('08:00')
        ->and($todaySchedule[1]->scheduled_time->format('H:i'))->toBe('14:00')
        ->and($todaySchedule[2]->scheduled_time->format('H:i'))->toBe('20:00');
});

it('does not generate schedule for PRN medications', function () {
    actingAs($this->user);

    $wardRound = WardRound::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'status' => 'in_progress',
    ]);

    $drug = Drug::factory()->create();

    $prescription = Prescription::factory()->create([
        'prescribable_type' => WardRound::class,
        'prescribable_id' => $wardRound->id,
        'drug_id' => $drug->id,
        'frequency' => 'PRN',
        'duration' => '5 days',
        'dose_quantity' => '500mg',
        'status' => 'prescribed',
    ]);

    // PRN medications should not generate scheduled administrations
    expect(MedicationAdministration::where('prescription_id', $prescription->id)->count())
        ->toBe(0);
});
