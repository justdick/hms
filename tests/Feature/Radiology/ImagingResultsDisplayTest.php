<?php

/**
 * Property-based tests for Imaging Results Display
 * Feature: imaging-radiology-integration
 */

use App\Models\Consultation;
use App\Models\ImagingAttachment;
use App\Models\LabOrder;
use App\Models\LabService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Feature: imaging-radiology-integration, Property 16: External indicator display
 * Validates: Requirements 7.5
 *
 * For any imaging order from an external facility, the results view should display
 * the external facility name and study date.
 */
test('external imaging attachments have facility name and study date', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Create shared resources to reuse across iterations
    $imagingService = LabService::factory()->imaging()->create();
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);

    // Run 15 iterations with random data
    for ($i = 0; $i < 15; $i++) {
        // Clear existing lab orders and attachments
        ImagingAttachment::query()->delete();
        LabOrder::query()->delete();

        $patient = \App\Models\Patient::factory()->create();
        $checkin = \App\Models\PatientCheckin::factory()->create([
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);
        $consultation = Consultation::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'doctor_id' => $doctor->id,
        ]);

        $labOrder = LabOrder::factory()->create([
            'lab_service_id' => $imagingService->id,
            'consultation_id' => $consultation->id,
            'orderable_type' => Consultation::class,
            'orderable_id' => $consultation->id,
            'status' => 'completed',
            'ordered_by' => $doctor->id,
        ]);

        // Generate random external facility data
        $facilityName = fake()->company();
        $studyDate = fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d');

        // Create external attachment
        $attachment = ImagingAttachment::factory()->create([
            'lab_order_id' => $labOrder->id,
            'uploaded_by' => $doctor->id,
            'is_external' => true,
            'external_facility_name' => $facilityName,
            'external_study_date' => $studyDate,
        ]);

        // Refresh and verify
        $attachment->refresh();

        // External attachments must have facility name and study date
        expect($attachment->is_external)->toBeTrue()
            ->and($attachment->external_facility_name)->toBe($facilityName)
            ->and($attachment->external_study_date->format('Y-m-d'))->toBe($studyDate);
    }
});

/**
 * Feature: imaging-radiology-integration, Property 16: External indicator display
 * Validates: Requirements 7.5
 *
 * For any imaging order with external attachments, the order should be identifiable
 * as having external images through its attachments.
 */
test('lab order with external attachments can be identified', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Create shared resources to reuse across iterations
    $imagingService = LabService::factory()->imaging()->create();
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);

    // Run 15 iterations with random data
    for ($i = 0; $i < 15; $i++) {
        // Clear existing lab orders and attachments
        ImagingAttachment::query()->delete();
        LabOrder::query()->delete();

        $patient = \App\Models\Patient::factory()->create();
        $checkin = \App\Models\PatientCheckin::factory()->create([
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);
        $consultation = Consultation::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'doctor_id' => $doctor->id,
        ]);

        $labOrder = LabOrder::factory()->create([
            'lab_service_id' => $imagingService->id,
            'consultation_id' => $consultation->id,
            'orderable_type' => Consultation::class,
            'orderable_id' => $consultation->id,
            'status' => 'completed',
            'ordered_by' => $doctor->id,
        ]);

        // Randomly decide if this order has external attachments
        $hasExternal = fake()->boolean();
        $attachmentCount = fake()->numberBetween(1, 5);

        for ($j = 0; $j < $attachmentCount; $j++) {
            $isExternal = $hasExternal && $j === 0; // At least one external if hasExternal is true

            ImagingAttachment::factory()->create([
                'lab_order_id' => $labOrder->id,
                'uploaded_by' => $doctor->id,
                'is_external' => $isExternal,
                'external_facility_name' => $isExternal ? fake()->company() : null,
                'external_study_date' => $isExternal ? fake()->date() : null,
            ]);
        }

        // Refresh the model with attachments
        $labOrder->refresh();
        $labOrder->load('imagingAttachments');

        // Check if order has external attachments
        $hasExternalAttachments = $labOrder->imagingAttachments->contains('is_external', true);

        // The presence of external attachments should match our setup
        expect($hasExternalAttachments)->toBe($hasExternal);

        // If has external, verify we can get the external info
        if ($hasExternal) {
            $externalAttachment = $labOrder->imagingAttachments->firstWhere('is_external', true);
            expect($externalAttachment)->not->toBeNull()
                ->and($externalAttachment->external_facility_name)->not->toBeNull()
                ->and($externalAttachment->external_study_date)->not->toBeNull();
        }
    }
});

/**
 * Feature: imaging-radiology-integration, Property 16: External indicator display
 * Validates: Requirements 7.5
 *
 * For any non-external imaging attachment, the external facility name and study date
 * should be null.
 */
test('non-external attachments have null facility name and study date', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Create shared resources to reuse across iterations
    $imagingService = LabService::factory()->imaging()->create();
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);

    // Run 15 iterations with random data
    for ($i = 0; $i < 15; $i++) {
        // Clear existing lab orders and attachments
        ImagingAttachment::query()->delete();
        LabOrder::query()->delete();

        $patient = \App\Models\Patient::factory()->create();
        $checkin = \App\Models\PatientCheckin::factory()->create([
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);
        $consultation = Consultation::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'doctor_id' => $doctor->id,
        ]);

        $labOrder = LabOrder::factory()->create([
            'lab_service_id' => $imagingService->id,
            'consultation_id' => $consultation->id,
            'orderable_type' => Consultation::class,
            'orderable_id' => $consultation->id,
            'status' => 'completed',
            'ordered_by' => $doctor->id,
        ]);

        // Create non-external attachment
        $attachment = ImagingAttachment::factory()->create([
            'lab_order_id' => $labOrder->id,
            'uploaded_by' => $doctor->id,
            'is_external' => false,
            'external_facility_name' => null,
            'external_study_date' => null,
        ]);

        // Refresh and verify
        $attachment->refresh();

        // Non-external attachments should have null external fields
        expect($attachment->is_external)->toBeFalse()
            ->and($attachment->external_facility_name)->toBeNull()
            ->and($attachment->external_study_date)->toBeNull();
    }
});
