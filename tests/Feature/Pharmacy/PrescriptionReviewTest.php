<?php

use App\Models\Charge;
use App\Models\Consultation;
use App\Models\Drug;
use App\Models\DrugBatch;
use App\Models\PatientCheckin;
use App\Models\Prescription;
use App\Models\User;
use App\Services\DispensingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->dispensingService = app(DispensingService::class);
    $this->pharmacist = User::factory()->create();
    $this->actingAs($this->pharmacist);
});

test('review prescription with keep action maintains full quantity', function () {
    $drug = Drug::factory()->create();
    DrugBatch::factory()->for($drug)->withQuantity(100)->create();

    $checkin = PatientCheckin::factory()->create();
    $consultation = Consultation::factory()->for($checkin, 'patientCheckin')->create();
    $prescription = Prescription::factory()
        ->for($consultation)
        ->for($drug)
        ->create(['quantity' => 50]);

    $reviewed = $this->dispensingService->reviewPrescription($prescription, [
        'action' => 'keep',
        'notes' => 'Stock available',
    ], $this->pharmacist);

    expect($reviewed->status)->toBe('reviewed')
        ->and($reviewed->quantity_to_dispense)->toBe(50)
        ->and($reviewed->reviewed_by)->toBe($this->pharmacist->id)
        ->and($reviewed->reviewed_at)->not->toBeNull()
        ->and($reviewed->dispensing_notes)->toBe('Stock available');
});

test('review prescription with partial action adjusts quantity and updates charge', function () {
    $drug = Drug::factory()->withSpecificPrice(100.00)->create();
    DrugBatch::factory()->for($drug)->withQuantity(30)->create();

    $checkin = PatientCheckin::factory()->create();
    $consultation = Consultation::factory()->for($checkin, 'patientCheckin')->create();

    // Create prescription without triggering observer
    $prescription = Prescription::withoutEvents(function () use ($consultation, $drug) {
        return Prescription::factory()
            ->for($consultation)
            ->for($drug)
            ->create(['quantity' => 50]);
    });

    // Create charge manually
    $charge = Charge::factory()
        ->for($checkin, 'patientCheckin')
        ->create([
            'prescription_id' => $prescription->id,
            'amount' => 5000.00, // 50 × 100
            'service_type' => 'pharmacy',
        ]);

    $reviewed = $this->dispensingService->reviewPrescription($prescription, [
        'action' => 'partial',
        'quantity_to_dispense' => 30,
        'notes' => 'Limited stock available',
    ], $this->pharmacist);

    expect($reviewed->status)->toBe('reviewed')
        ->and($reviewed->quantity_to_dispense)->toBe(30);

    $charge->refresh();
    expect($charge->amount)->toBe('3000.00'); // 30 × 100
});

test('review prescription with external action voids charge', function () {
    $drug = Drug::factory()->create();
    $checkin = PatientCheckin::factory()->create();
    $consultation = Consultation::factory()->for($checkin, 'patientCheckin')->create();

    // Create prescription without triggering observer
    $prescription = Prescription::withoutEvents(function () use ($consultation, $drug) {
        return Prescription::factory()
            ->for($consultation)
            ->for($drug)
            ->create();
    });

    $charge = Charge::factory()
        ->for($checkin, 'patientCheckin')
        ->create([
            'prescription_id' => $prescription->id,
            'amount' => 1000.00,
            'service_type' => 'pharmacy',
        ]);

    $reviewed = $this->dispensingService->reviewPrescription($prescription, [
        'action' => 'external',
        'reason' => 'Out of stock - patient to buy externally',
    ], $this->pharmacist);

    expect($reviewed->status)->toBe('not_dispensed')
        ->and($reviewed->external_reason)->toBe('Out of stock - patient to buy externally');

    $charge->refresh();
    expect($charge->status)->toBe('cancelled')
        ->and($charge->amount)->toBe('0.00');
});

test('review prescription with cancel action voids charge', function () {
    $drug = Drug::factory()->create();
    $checkin = PatientCheckin::factory()->create();
    $consultation = Consultation::factory()->for($checkin, 'patientCheckin')->create();

    // Create prescription without triggering observer
    $prescription = Prescription::withoutEvents(function () use ($consultation, $drug) {
        return Prescription::factory()
            ->for($consultation)
            ->for($drug)
            ->create();
    });

    $charge = Charge::factory()
        ->for($checkin, 'patientCheckin')
        ->create([
            'prescription_id' => $prescription->id,
            'amount' => 500.00,
            'service_type' => 'pharmacy',
        ]);

    $reviewed = $this->dispensingService->reviewPrescription($prescription, [
        'action' => 'cancel',
        'reason' => 'Doctor prescription error',
    ], $this->pharmacist);

    expect($reviewed->status)->toBe('cancelled')
        ->and($reviewed->dispensing_notes)->toContain('Doctor prescription error');

    $charge->refresh();
    expect($charge->status)->toBe('cancelled');
});

test('get prescriptions for review returns correct data with stock status', function () {
    $drug1 = Drug::factory()->create();
    $drug2 = Drug::factory()->create();

    DrugBatch::factory()->for($drug1)->withQuantity(100)->create();
    DrugBatch::factory()->for($drug2)->withQuantity(20)->create();

    $checkin = PatientCheckin::factory()->create();
    $consultation = Consultation::factory()->for($checkin, 'patientCheckin')->create();

    $prescription1 = Prescription::factory()->for($consultation)->for($drug1)->create(['quantity' => 50]);
    $prescription2 = Prescription::factory()->for($consultation)->for($drug2)->create(['quantity' => 30]);

    $result = $this->dispensingService->getPrescriptionsForReview($checkin->id);

    expect($result)->toHaveCount(2);

    expect($result[0]['prescription']->id)->toBe($prescription1->id)
        ->and($result[0]['can_dispense_full'])->toBeTrue()
        ->and($result[0]['max_dispensable'])->toBe(100);

    expect($result[1]['prescription']->id)->toBe($prescription2->id)
        ->and($result[1]['can_dispense_full'])->toBeFalse()
        ->and($result[1]['max_dispensable'])->toBe(20);
});
