<?php

/**
 * Property-Based Test for Claim Items Aggregation for Admissions
 *
 * **Feature: nhis-claims-integration, Property 21: Claim Items Aggregation for Admissions**
 * **Validates: Requirements 11.4**
 *
 * Property: For any claim associated with an admission, the displayed items should include
 * items from both the initial consultation and all ward rounds.
 */

use App\Models\Consultation;
use App\Models\ConsultationProcedure;
use App\Models\Drug;
use App\Models\LabOrder;
use App\Models\LabService;
use App\Models\MinorProcedureType;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\PatientCheckin;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardRound;
use App\Models\WardRoundProcedure;
use App\Services\ClaimVettingService;

beforeEach(function () {
    // Clean up
    WardRoundProcedure::query()->delete();
    WardRound::query()->delete();
    PatientAdmission::query()->delete();
});

/**
 * Helper function to create a ward round with required fields
 */
function createWardRound(int $admissionId, int $doctorId, int $dayNumber): WardRound
{
    return WardRound::create([
        'patient_admission_id' => $admissionId,
        'doctor_id' => $doctorId,
        'day_number' => $dayNumber,
        'round_type' => 'daily_round',
        'round_datetime' => now(),
        'status' => 'completed',
    ]);
}

/**
 * Helper function to create a consultation procedure with required fields
 */
function createConsultationProcedure(int $consultationId, int $doctorId, int $procedureTypeId): ConsultationProcedure
{
    return ConsultationProcedure::create([
        'consultation_id' => $consultationId,
        'doctor_id' => $doctorId,
        'minor_procedure_type_id' => $procedureTypeId,
        'performed_at' => now(),
    ]);
}

/**
 * Helper function to create a ward round procedure with required fields
 */
function createWardRoundProcedure(int $wardRoundId, int $doctorId, int $procedureTypeId): WardRoundProcedure
{
    return WardRoundProcedure::create([
        'ward_round_id' => $wardRoundId,
        'doctor_id' => $doctorId,
        'minor_procedure_type_id' => $procedureTypeId,
        'performed_at' => now(),
    ]);
}

/**
 * Generate random ward round counts for property testing
 */
dataset('random_ward_round_counts', function () {
    return [
        [0],
        [1],
        [2],
        [3],
        [5],
    ];
});

it('aggregates items from initial consultation', function () {
    // Arrange: Create admission with consultation
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();
    $ward = Ward::factory()->create();

    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
    ]);

    $consultation = Consultation::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'doctor_id' => $doctor->id,
    ]);

    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'consultation_id' => $consultation->id,
        'ward_id' => $ward->id,
    ]);

    // Create items on consultation - use create() directly to avoid observer issues
    $labService = LabService::factory()->create();
    $drug = Drug::factory()->create();

    LabOrder::create([
        'orderable_type' => Consultation::class,
        'orderable_id' => $consultation->id,
        'lab_service_id' => $labService->id,
        'ordered_by' => $doctor->id,
        'ordered_at' => now(),
        'status' => 'pending',
        'priority' => 'routine',
    ]);

    // Create prescription without triggering observer
    Prescription::withoutEvents(function () use ($consultation, $drug) {
        Prescription::create([
            'prescribable_type' => Consultation::class,
            'prescribable_id' => $consultation->id,
            'consultation_id' => $consultation->id,
            'drug_id' => $drug->id,
            'medication_name' => $drug->name,
            'frequency' => 'TDS',
            'duration' => '7 days',
            'quantity' => 21,
            'status' => 'prescribed',
        ]);
    });

    // Act
    $service = app(ClaimVettingService::class);
    $aggregatedItems = $service->aggregateAdmissionItems($admission);

    // Assert: Items from consultation should be included
    expect($aggregatedItems['lab_orders'])->toHaveCount(1)
        ->and($aggregatedItems['prescriptions'])->toHaveCount(1)
        ->and($aggregatedItems['lab_orders']->first()->lab_service_id)->toBe($labService->id)
        ->and($aggregatedItems['prescriptions']->first()->drug_id)->toBe($drug->id);
});

it('aggregates items from ward rounds', function (int $wardRoundCount) {
    // Arrange: Create admission
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();
    $ward = Ward::factory()->create();

    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
    ]);

    $consultation = Consultation::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'doctor_id' => $doctor->id,
    ]);

    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'consultation_id' => $consultation->id,
        'ward_id' => $ward->id,
    ]);

    // Create ward rounds with items
    $expectedLabOrders = 0;
    $expectedPrescriptions = 0;

    for ($i = 0; $i < $wardRoundCount; $i++) {
        $wardRound = createWardRound($admission->id, $doctor->id, $i + 1);

        // Add lab order to each ward round
        $labService = LabService::factory()->create();
        LabOrder::create([
            'orderable_type' => WardRound::class,
            'orderable_id' => $wardRound->id,
            'lab_service_id' => $labService->id,
            'ordered_by' => $doctor->id,
            'ordered_at' => now(),
            'status' => 'pending',
            'priority' => 'routine',
        ]);
        $expectedLabOrders++;

        // Add prescription to each ward round
        $drug = Drug::factory()->create();
        Prescription::withoutEvents(function () use ($wardRound, $drug) {
            Prescription::create([
                'prescribable_type' => WardRound::class,
                'prescribable_id' => $wardRound->id,
                'drug_id' => $drug->id,
                'medication_name' => $drug->name,
                'frequency' => 'TDS',
                'duration' => '7 days',
                'quantity' => 21,
                'status' => 'prescribed',
            ]);
        });
        $expectedPrescriptions++;
    }

    // Act
    $service = app(ClaimVettingService::class);
    $aggregatedItems = $service->aggregateAdmissionItems($admission);

    // Assert: Items from all ward rounds should be included
    expect($aggregatedItems['lab_orders'])->toHaveCount($expectedLabOrders)
        ->and($aggregatedItems['prescriptions'])->toHaveCount($expectedPrescriptions);
})->with('random_ward_round_counts');

it('aggregates items from both consultation and ward rounds', function (int $wardRoundCount) {
    // Arrange: Create admission with consultation
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();
    $ward = Ward::factory()->create();

    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
    ]);

    $consultation = Consultation::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'doctor_id' => $doctor->id,
    ]);

    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'consultation_id' => $consultation->id,
        'ward_id' => $ward->id,
    ]);

    // Create items on consultation
    $consultationLabService = LabService::factory()->create();
    $consultationDrug = Drug::factory()->create();

    LabOrder::create([
        'orderable_type' => Consultation::class,
        'orderable_id' => $consultation->id,
        'lab_service_id' => $consultationLabService->id,
        'ordered_by' => $doctor->id,
        'ordered_at' => now(),
        'status' => 'pending',
        'priority' => 'routine',
    ]);

    Prescription::withoutEvents(function () use ($consultation, $consultationDrug) {
        Prescription::create([
            'prescribable_type' => Consultation::class,
            'prescribable_id' => $consultation->id,
            'consultation_id' => $consultation->id,
            'drug_id' => $consultationDrug->id,
            'medication_name' => $consultationDrug->name,
            'frequency' => 'TDS',
            'duration' => '7 days',
            'quantity' => 21,
            'status' => 'prescribed',
        ]);
    });

    // Create ward rounds with items
    for ($i = 0; $i < $wardRoundCount; $i++) {
        $wardRound = createWardRound($admission->id, $doctor->id, $i + 1);

        $labService = LabService::factory()->create();
        LabOrder::create([
            'orderable_type' => WardRound::class,
            'orderable_id' => $wardRound->id,
            'lab_service_id' => $labService->id,
            'ordered_by' => $doctor->id,
            'ordered_at' => now(),
            'status' => 'pending',
            'priority' => 'routine',
        ]);

        $drug = Drug::factory()->create();
        Prescription::withoutEvents(function () use ($wardRound, $drug) {
            Prescription::create([
                'prescribable_type' => WardRound::class,
                'prescribable_id' => $wardRound->id,
                'drug_id' => $drug->id,
                'medication_name' => $drug->name,
                'frequency' => 'TDS',
                'duration' => '7 days',
                'quantity' => 21,
                'status' => 'prescribed',
            ]);
        });
    }

    // Act
    $service = app(ClaimVettingService::class);
    $aggregatedItems = $service->aggregateAdmissionItems($admission);

    // Assert: Total items = consultation items + ward round items
    $expectedLabOrders = 1 + $wardRoundCount; // 1 from consultation + 1 per ward round
    $expectedPrescriptions = 1 + $wardRoundCount;

    expect($aggregatedItems['lab_orders'])->toHaveCount($expectedLabOrders)
        ->and($aggregatedItems['prescriptions'])->toHaveCount($expectedPrescriptions);
})->with('random_ward_round_counts');

it('aggregates procedures from consultation and ward rounds', function () {
    // Arrange: Create admission
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();
    $ward = Ward::factory()->create();

    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
    ]);

    $consultation = Consultation::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'doctor_id' => $doctor->id,
    ]);

    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'consultation_id' => $consultation->id,
        'ward_id' => $ward->id,
    ]);

    // Create procedure type
    $procedureType = MinorProcedureType::factory()->create();

    // Create procedure on consultation
    createConsultationProcedure($consultation->id, $doctor->id, $procedureType->id);

    // Create ward round with procedure
    $wardRound = createWardRound($admission->id, $doctor->id, 1);
    createWardRoundProcedure($wardRound->id, $doctor->id, $procedureType->id);

    // Act
    $service = app(ClaimVettingService::class);
    $aggregatedItems = $service->aggregateAdmissionItems($admission);

    // Assert: Procedures from both consultation and ward round should be included
    expect($aggregatedItems['procedures'])->toHaveCount(2);
});

it('returns empty collections when admission has no items', function () {
    // Arrange: Create admission without any items
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();
    $ward = Ward::factory()->create();

    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
    ]);

    $consultation = Consultation::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'doctor_id' => $doctor->id,
    ]);

    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'consultation_id' => $consultation->id,
        'ward_id' => $ward->id,
    ]);

    // Act
    $service = app(ClaimVettingService::class);
    $aggregatedItems = $service->aggregateAdmissionItems($admission);

    // Assert: All collections should be empty
    expect($aggregatedItems['lab_orders'])->toBeEmpty()
        ->and($aggregatedItems['prescriptions'])->toBeEmpty()
        ->and($aggregatedItems['procedures'])->toBeEmpty();
});
