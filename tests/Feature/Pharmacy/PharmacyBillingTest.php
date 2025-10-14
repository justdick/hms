<?php

use App\Models\BillingConfiguration;
use App\Models\Charge;
use App\Models\Consultation;
use App\Models\Drug;
use App\Models\PatientCheckin;
use App\Models\Prescription;
use App\Models\User;
use App\Services\PharmacyBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->billingService = app(PharmacyBillingService::class);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('create charge for prescription creates correct charge', function () {
    $drug = Drug::factory()->withSpecificPrice(100.00)->create(['unit_price' => 100.00]);
    $checkin = PatientCheckin::factory()->create();
    $consultation = Consultation::factory()->for($checkin, 'patientCheckin')->create();
    $prescription = Prescription::factory()
        ->for($consultation)
        ->for($drug)
        ->create(['quantity' => 10]);

    $charge = $this->billingService->createChargeForPrescription($prescription);

    expect($charge)->toBeInstanceOf(Charge::class)
        ->and($charge->prescription_id)->toBe($prescription->id)
        ->and($charge->patient_checkin_id)->toBe($checkin->id)
        ->and($charge->amount)->toBe('1000.00') // 100 × 10
        ->and($charge->service_type)->toBe('pharmacy')
        ->and($charge->status)->toBe('pending');
});

test('update charge for review adjusts amount correctly', function () {
    $drug = Drug::factory()->withSpecificPrice(50.00)->create();
    $checkin = PatientCheckin::factory()->create();
    $consultation = Consultation::factory()->for($checkin, 'patientCheckin')->create();
    $prescription = Prescription::factory()
        ->for($consultation)
        ->for($drug)
        ->create(['quantity' => 20]);

    $charge = $this->billingService->createChargeForPrescription($prescription);

    expect($charge->amount)->toBe('1000.00'); // 50 × 20

    $updatedCharge = $this->billingService->updateChargeForReview($prescription, 10, 'Stock limitation');

    expect($updatedCharge->amount)->toBe('500.00') // 50 × 10
        ->and($updatedCharge->notes)->toContain('Stock limitation');
});

test('void charge for external sets status to cancelled and amount to zero', function () {
    $drug = Drug::factory()->create();
    $checkin = PatientCheckin::factory()->create();
    $consultation = Consultation::factory()->for($checkin, 'patientCheckin')->create();
    $prescription = Prescription::factory()
        ->for($consultation)
        ->for($drug)
        ->create();

    $charge = $this->billingService->createChargeForPrescription($prescription);
    $originalAmount = $charge->amount;

    $voidedCharge = $this->billingService->voidChargeForExternal($prescription, 'Patient will buy from outside');

    expect($voidedCharge->status)->toBe('cancelled')
        ->and($voidedCharge->amount)->toBe('0.00')
        ->and($voidedCharge->notes)->toContain('Patient will buy from outside');
});

test('can dispense returns true when payment is not required by configuration', function () {
    BillingConfiguration::setValue('pharmacy.require_payment_before_dispensing', false, 'pharmacy');

    $prescription = Prescription::factory()->reviewed()->create();

    expect($this->billingService->canDispense($prescription))->toBeTrue();
});

test('can dispense returns false when payment required but not paid', function () {
    BillingConfiguration::setValue('pharmacy.require_payment_before_dispensing', true, 'pharmacy');

    $drug = Drug::factory()->create();
    $checkin = PatientCheckin::factory()->create();
    $consultation = Consultation::factory()->for($checkin, 'patientCheckin')->create();
    $prescription = Prescription::factory()
        ->for($consultation)
        ->for($drug)
        ->reviewed()
        ->create();

    $this->billingService->createChargeForPrescription($prescription);

    expect($this->billingService->canDispense($prescription))->toBeFalse();
});

test('can dispense returns true when charge is paid', function () {
    BillingConfiguration::setValue('pharmacy.require_payment_before_dispensing', true, 'pharmacy');

    $drug = Drug::factory()->create();
    $checkin = PatientCheckin::factory()->create();
    $consultation = Consultation::factory()->for($checkin, 'patientCheckin')->create();
    $prescription = Prescription::factory()
        ->for($consultation)
        ->for($drug)
        ->reviewed()
        ->create();

    $charge = $this->billingService->createChargeForPrescription($prescription);
    $charge->update(['status' => 'paid']);

    expect($this->billingService->canDispense($prescription))->toBeTrue();
});

test('can dispense returns true when charge is waived', function () {
    BillingConfiguration::setValue('pharmacy.require_payment_before_dispensing', true, 'pharmacy');

    $drug = Drug::factory()->create();
    $checkin = PatientCheckin::factory()->create();
    $consultation = Consultation::factory()->for($checkin, 'patientCheckin')->create();
    $prescription = Prescription::factory()
        ->for($consultation)
        ->for($drug)
        ->reviewed()
        ->create();

    $charge = $this->billingService->createChargeForPrescription($prescription);
    $charge->update(['status' => 'waived']);

    expect($this->billingService->canDispense($prescription))->toBeTrue();
});

test('get payment status summary returns correct totals', function () {
    $checkin = PatientCheckin::factory()->create();
    $consultation = Consultation::factory()->for($checkin, 'patientCheckin')->create();

    // Create prescriptions without observers to prevent auto-charge creation
    Prescription::withoutEvents(function () use ($consultation, $checkin) {
        $prescription1 = Prescription::factory()->for($consultation)->create();
        $prescription2 = Prescription::factory()->for($consultation)->create();
        $prescription3 = Prescription::factory()->for($consultation)->create();

        Charge::factory()->for($checkin, 'patientCheckin')->pending()->create([
            'amount' => 500,
            'service_type' => 'pharmacy',
            'prescription_id' => $prescription1->id,
        ]);
        Charge::factory()->for($checkin, 'patientCheckin')->paid()->create([
            'amount' => 300,
            'service_type' => 'pharmacy',
            'prescription_id' => $prescription2->id,
        ]);
        Charge::factory()->for($checkin, 'patientCheckin')->paid()->create([
            'amount' => 200,
            'service_type' => 'pharmacy',
            'prescription_id' => $prescription3->id,
        ]);
    });

    $summary = $this->billingService->getPaymentStatusSummary($checkin->id);

    expect($summary['total_amount'])->toBe(1000.0)
        ->and($summary['paid_amount'])->toBe(500.0)
        ->and($summary['pending_amount'])->toBe(500.0)
        ->and($summary['all_paid'])->toBeFalse()
        ->and($summary['has_pending'])->toBeTrue()
        ->and($summary['charges_count'])->toBe(3)
        ->and($summary['paid_charges_count'])->toBe(2)
        ->and($summary['pending_charges_count'])->toBe(1);
});
