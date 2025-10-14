<?php

use App\Models\BillingConfiguration;
use App\Models\Charge;
use App\Models\Consultation;
use App\Models\Dispensing;
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

    // Set default billing configuration
    BillingConfiguration::setValue('pharmacy.require_payment_before_dispensing', true, 'pharmacy');
});

test('dispense prescription successfully when payment is made', function () {
    $drug = Drug::factory()->create();
    $batch = DrugBatch::factory()->for($drug)->withQuantity(100)->create();

    $checkin = PatientCheckin::factory()->create();
    $consultation = Consultation::factory()->for($checkin, 'patientCheckin')->create();
    $prescription = Prescription::factory()
        ->for($consultation)
        ->for($drug)
        ->reviewed()
        ->create(['quantity' => 50, 'quantity_to_dispense' => 50]);

    // Create and pay charge
    $charge = Charge::factory()
        ->for($checkin, 'patientCheckin')
        ->paid()
        ->create(['prescription_id' => $prescription->id]);

    $dispensing = $this->dispensingService->dispensePrescription($prescription, [
        'notes' => 'Dispensed successfully',
    ], $this->pharmacist);

    expect($dispensing)->toBeInstanceOf(Dispensing::class)
        ->and($dispensing->prescription_id)->toBe($prescription->id)
        ->and($dispensing->quantity)->toBe(50);

    $prescription->refresh();
    expect($prescription->status)->toBe('dispensed')
        ->and($prescription->quantity_dispensed)->toBe(50);

    $batch->refresh();
    expect($batch->quantity_remaining)->toBe(50); // 100 - 50
});

test('dispense prescription throws exception when payment not made', function () {
    $drug = Drug::factory()->create();
    DrugBatch::factory()->for($drug)->withQuantity(100)->create();

    $checkin = PatientCheckin::factory()->create();
    $consultation = Consultation::factory()->for($checkin, 'patientCheckin')->create();
    $prescription = Prescription::factory()
        ->for($consultation)
        ->for($drug)
        ->reviewed()
        ->create(['quantity_to_dispense' => 50]);

    // Create unpaid charge
    Charge::factory()
        ->for($checkin, 'patientCheckin')
        ->pending()
        ->create(['prescription_id' => $prescription->id]);

    expect(fn () => $this->dispensingService->dispensePrescription($prescription, [], $this->pharmacist))
        ->toThrow(Exception::class, 'Payment required before dispensing');
});

test('dispense prescription when payment not required by configuration', function () {
    BillingConfiguration::setValue('pharmacy.require_payment_before_dispensing', false, 'pharmacy');

    $drug = Drug::factory()->create();
    DrugBatch::factory()->for($drug)->withQuantity(100)->create();

    $checkin = PatientCheckin::factory()->create();
    $consultation = Consultation::factory()->for($checkin, 'patientCheckin')->create();
    $prescription = Prescription::factory()
        ->for($consultation)
        ->for($drug)
        ->reviewed()
        ->create(['quantity_to_dispense' => 30]);

    $dispensing = $this->dispensingService->dispensePrescription($prescription, [], $this->pharmacist);

    expect($dispensing)->toBeInstanceOf(Dispensing::class)
        ->and($dispensing->quantity)->toBe(30);

    $prescription->refresh();
    expect($prescription->status)->toBe('dispensed');
});

test('dispense prescription deducts stock from multiple batches using FIFO', function () {
    $drug = Drug::factory()->create();
    $batch1 = DrugBatch::factory()->for($drug)->withQuantity(30)->create(['expiry_date' => now()->addMonths(6)]);
    $batch2 = DrugBatch::factory()->for($drug)->withQuantity(50)->create(['expiry_date' => now()->addMonths(12)]);

    $checkin = PatientCheckin::factory()->create();
    $consultation = Consultation::factory()->for($checkin, 'patientCheckin')->create();
    $prescription = Prescription::factory()
        ->for($consultation)
        ->for($drug)
        ->reviewed()
        ->create(['quantity_to_dispense' => 50]);

    Charge::factory()->for($checkin, 'patientCheckin')->paid()->create(['prescription_id' => $prescription->id]);

    $dispensing = $this->dispensingService->dispensePrescription($prescription, [], $this->pharmacist);

    $batch1->refresh();
    $batch2->refresh();

    expect($batch1->quantity_remaining)->toBe(0) // Used all 30 from first batch
        ->and($batch2->quantity_remaining)->toBe(30); // Used 20 from second batch (30 + 20 = 50)
});

test('dispense prescription throws exception when insufficient stock', function () {
    $drug = Drug::factory()->create();
    DrugBatch::factory()->for($drug)->withQuantity(20)->create();

    $checkin = PatientCheckin::factory()->create();
    $consultation = Consultation::factory()->for($checkin, 'patientCheckin')->create();
    $prescription = Prescription::factory()
        ->for($consultation)
        ->for($drug)
        ->reviewed()
        ->create(['quantity_to_dispense' => 50]);

    Charge::factory()->for($checkin, 'patientCheckin')->paid()->create(['prescription_id' => $prescription->id]);

    expect(fn () => $this->dispensingService->dispensePrescription($prescription, [], $this->pharmacist))
        ->toThrow(Exception::class, 'Insufficient stock');
});

test('partial dispense updates prescription status correctly', function () {
    $drug = Drug::factory()->create();
    DrugBatch::factory()->for($drug)->withQuantity(100)->create();

    $checkin = PatientCheckin::factory()->create();
    $consultation = Consultation::factory()->for($checkin, 'patientCheckin')->create();
    $prescription = Prescription::factory()
        ->for($consultation)
        ->for($drug)
        ->reviewed()
        ->create(['quantity' => 100, 'quantity_to_dispense' => 100, 'quantity_dispensed' => 0]);

    Charge::factory()->for($checkin, 'patientCheckin')->paid()->create(['prescription_id' => $prescription->id]);

    // Dispense 30 out of 100
    $dispensing = $this->dispensingService->partialDispense($prescription, 30, [
        'notes' => 'Partial dispensing - patient will collect rest later',
    ], $this->pharmacist);

    $prescription->refresh();
    expect($prescription->status)->toBe('partially_dispensed')
        ->and($prescription->quantity_dispensed)->toBe(30);

    // Dispense remaining 70
    $dispensing2 = $this->dispensingService->partialDispense($prescription, 70, [], $this->pharmacist);

    $prescription->refresh();
    expect($prescription->status)->toBe('dispensed') // Status should change to dispensed
        ->and($prescription->quantity_dispensed)->toBe(100);
});

test('get prescriptions for dispensing returns correct payment status', function () {
    $drug1 = Drug::factory()->create();
    $drug2 = Drug::factory()->create();

    DrugBatch::factory()->for($drug1)->withQuantity(100)->create();
    DrugBatch::factory()->for($drug2)->withQuantity(100)->create();

    $checkin = PatientCheckin::factory()->create();
    $consultation = Consultation::factory()->for($checkin, 'patientCheckin')->create();

    $prescription1 = Prescription::factory()->for($consultation)->for($drug1)->reviewed()->create();
    $prescription2 = Prescription::factory()->for($consultation)->for($drug2)->reviewed()->create();

    // First prescription is paid
    Charge::factory()->for($checkin, 'patientCheckin')->paid()->create(['prescription_id' => $prescription1->id]);

    // Second prescription is pending
    Charge::factory()->for($checkin, 'patientCheckin')->pending()->create(['prescription_id' => $prescription2->id]);

    $result = $this->dispensingService->getPrescriptionsForDispensing($checkin->id);

    expect($result)->toHaveCount(2);

    expect($result[0]['prescription']->id)->toBe($prescription1->id)
        ->and($result[0]['payment_status'])->toBe('paid')
        ->and($result[0]['can_dispense'])->toBeTrue();

    expect($result[1]['prescription']->id)->toBe($prescription2->id)
        ->and($result[1]['payment_status'])->toBe('pending')
        ->and($result[1]['can_dispense'])->toBeFalse();
});

test('validate payment status returns correct values', function () {
    $drug = Drug::factory()->create();
    $checkin = PatientCheckin::factory()->create();
    $consultation = Consultation::factory()->for($checkin, 'patientCheckin')->create();

    $paidPrescription = Prescription::factory()->for($consultation)->for($drug)->reviewed()->create();
    $unpaidPrescription = Prescription::factory()->for($consultation)->for($drug)->reviewed()->create();

    Charge::factory()->for($checkin, 'patientCheckin')->paid()->create(['prescription_id' => $paidPrescription->id]);
    Charge::factory()->for($checkin, 'patientCheckin')->pending()->create(['prescription_id' => $unpaidPrescription->id]);

    expect($this->dispensingService->validatePaymentStatus($paidPrescription))->toBeTrue()
        ->and($this->dispensingService->validatePaymentStatus($unpaidPrescription))->toBeFalse();
});

test('dispensing tracks batch information correctly', function () {
    $drug = Drug::factory()->create();
    $batch = DrugBatch::factory()->for($drug)->withQuantity(100)->create(['batch_number' => 'BATCH-123456']);

    $checkin = PatientCheckin::factory()->create();
    $consultation = Consultation::factory()->for($checkin, 'patientCheckin')->create();
    $prescription = Prescription::factory()
        ->for($consultation)
        ->for($drug)
        ->reviewed()
        ->create(['quantity_to_dispense' => 50]);

    Charge::factory()->for($checkin, 'patientCheckin')->paid()->create(['prescription_id' => $prescription->id]);

    $dispensing = $this->dispensingService->dispensePrescription($prescription, [], $this->pharmacist);

    $batchInfo = json_decode($dispensing->batch_info, true);

    expect($batchInfo)->toBeArray()
        ->and($batchInfo[0]['batch_number'])->toBe('BATCH-123456')
        ->and($batchInfo[0]['quantity'])->toBe(50);
});
