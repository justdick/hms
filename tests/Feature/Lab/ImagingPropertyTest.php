<?php

/**
 * Property-based tests for Imaging/Radiology Integration
 * Feature: imaging-radiology-integration
 */

use App\Models\LabService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Feature: imaging-radiology-integration, Property 1: Imaging flag persistence
 * Validates: Requirements 1.1
 *
 * For any lab service, setting `is_imaging` to true and saving should result
 * in the persisted value being true when retrieved.
 */
test('imaging flag persists correctly when set to true', function () {
    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
        $labService = LabService::factory()->create([
            'is_imaging' => true,
            'modality' => fake()->randomElement(['X-Ray', 'CT', 'MRI', 'Ultrasound', 'Mammography']),
        ]);

        // Retrieve fresh from database
        $retrieved = LabService::find($labService->id);

        expect($retrieved->is_imaging)->toBeTrue()
            ->and($retrieved->is_imaging)->toBeBool();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 1: Imaging flag persistence
 * Validates: Requirements 1.1
 *
 * For any lab service, setting `is_imaging` to false and saving should result
 * in the persisted value being false when retrieved.
 */
test('imaging flag persists correctly when set to false', function () {
    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
        $labService = LabService::factory()->create([
            'is_imaging' => false,
            'modality' => null,
        ]);

        // Retrieve fresh from database
        $retrieved = LabService::find($labService->id);

        expect($retrieved->is_imaging)->toBeFalse()
            ->and($retrieved->is_imaging)->toBeBool();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 1: Imaging flag persistence
 * Validates: Requirements 1.1
 *
 * For any lab service, the imaging scope should return only services with is_imaging = true.
 */
test('imaging scope returns only imaging services', function () {
    // Create a mix of imaging and laboratory services
    $imagingCount = fake()->numberBetween(5, 15);
    $labCount = fake()->numberBetween(5, 15);

    LabService::factory()->count($imagingCount)->imaging()->create();
    LabService::factory()->count($labCount)->laboratory()->create();

    $imagingServices = LabService::imaging()->get();

    expect($imagingServices)->toHaveCount($imagingCount);

    foreach ($imagingServices as $service) {
        expect($service->is_imaging)->toBeTrue();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 1: Imaging flag persistence
 * Validates: Requirements 1.1
 *
 * For any lab service, the laboratory scope should return only services with is_imaging = false.
 */
test('laboratory scope returns only laboratory services', function () {
    // Create a mix of imaging and laboratory services
    $imagingCount = fake()->numberBetween(5, 15);
    $labCount = fake()->numberBetween(5, 15);

    LabService::factory()->count($imagingCount)->imaging()->create();
    LabService::factory()->count($labCount)->laboratory()->create();

    $labServices = LabService::laboratory()->get();

    expect($labServices)->toHaveCount($labCount);

    foreach ($labServices as $service) {
        expect($service->is_imaging)->toBeFalse();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 2: Imaging attachments relationship
 * Validates: Requirements 1.3
 *
 * For any lab order with imaging attachments, the count of attachments retrieved
 * through the relationship should equal the count of attachments created for that order.
 */
test('imaging attachments relationship returns correct count', function () {
    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
        $labOrder = \App\Models\LabOrder::factory()->create();
        $attachmentCount = fake()->numberBetween(1, 5);

        \App\Models\ImagingAttachment::factory()
            ->count($attachmentCount)
            ->create(['lab_order_id' => $labOrder->id]);

        // Retrieve fresh from database
        $retrieved = \App\Models\LabOrder::with('imagingAttachments')->find($labOrder->id);

        expect($retrieved->imagingAttachments)->toHaveCount($attachmentCount);
    }
});

/**
 * Feature: imaging-radiology-integration, Property 3: Attachment metadata completeness
 * Validates: Requirements 1.4
 *
 * For any uploaded imaging attachment, all required fields (file_path, file_name,
 * file_type, file_size, uploaded_by, uploaded_at) should be non-null.
 */
test('attachment metadata is complete for all required fields', function () {
    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
        $attachment = \App\Models\ImagingAttachment::factory()->create();

        // Retrieve fresh from database
        $retrieved = \App\Models\ImagingAttachment::find($attachment->id);

        // All required fields must be non-null
        expect($retrieved->file_path)->not->toBeNull()
            ->and($retrieved->file_name)->not->toBeNull()
            ->and($retrieved->file_type)->not->toBeNull()
            ->and($retrieved->file_size)->not->toBeNull()
            ->and($retrieved->uploaded_by)->not->toBeNull()
            ->and($retrieved->uploaded_at)->not->toBeNull()
            ->and($retrieved->lab_order_id)->not->toBeNull();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 3: Attachment metadata completeness
 * Validates: Requirements 1.4
 *
 * For any imaging attachment, the uploadedBy relationship should return the correct user.
 */
test('attachment uploadedBy relationship returns correct user', function () {
    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
        $user = \App\Models\User::factory()->create();
        $attachment = \App\Models\ImagingAttachment::factory()->create([
            'uploaded_by' => $user->id,
        ]);

        // Retrieve fresh from database with relationship
        $retrieved = \App\Models\ImagingAttachment::with('uploadedBy')->find($attachment->id);

        expect($retrieved->uploadedBy)->not->toBeNull()
            ->and($retrieved->uploadedBy->id)->toBe($user->id);
    }
});

/**
 * Feature: imaging-radiology-integration, Property 3: Attachment metadata completeness
 * Validates: Requirements 1.4
 *
 * For any imaging attachment, the labOrder relationship should return the correct lab order.
 */
test('attachment labOrder relationship returns correct order', function () {
    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
        $labOrder = \App\Models\LabOrder::factory()->create();
        $attachment = \App\Models\ImagingAttachment::factory()->create([
            'lab_order_id' => $labOrder->id,
        ]);

        // Retrieve fresh from database with relationship
        $retrieved = \App\Models\ImagingAttachment::with('labOrder')->find($attachment->id);

        expect($retrieved->labOrder)->not->toBeNull()
            ->and($retrieved->labOrder->id)->toBe($labOrder->id);
    }
});
