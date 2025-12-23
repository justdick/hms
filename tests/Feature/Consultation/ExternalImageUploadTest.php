<?php

/**
 * Property-based tests for External Image Upload
 * Feature: imaging-radiology-integration
 */

use App\Models\Consultation;
use App\Models\ImagingAttachment;
use App\Models\LabOrder;
use App\Models\LabService;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed permissions
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Fake storage
    Storage::fake('local');
});

/**
 * Feature: imaging-radiology-integration, Property 4: External image metadata
 * Validates: Requirements 1.5, 4.2
 *
 * For any imaging attachment marked as external, the external_facility_name
 * and external_study_date fields should be non-null.
 */
test('external images require facility name and study date', function () {
    // Create shared resources
    $imagingService = LabService::factory()->imaging()->create();
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);
    $doctor->givePermissionTo('investigations.upload-external');
    $doctor->givePermissionTo('consultations.update-own');

    // Run 10 iterations with random data
    for ($i = 0; $i < 10; $i++) {
        $patient = Patient::factory()->create();
        $checkin = PatientCheckin::factory()->create([
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);
        $consultation = Consultation::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'doctor_id' => $doctor->id,
        ]);

        // Generate random external facility name and study date
        $facilityName = fake()->company();
        $studyDate = fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d');

        // Create a fake file
        $file = UploadedFile::fake()->image('test_image.jpg', 100, 100);

        // Upload external image
        $response = $this->actingAs($doctor)->post(
            route('consultation.external-images.store', $consultation),
            [
                'lab_service_id' => $imagingService->id,
                'external_facility_name' => $facilityName,
                'external_study_date' => $studyDate,
                'files' => [$file],
                'descriptions' => ['Test image'],
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify the attachment was created with external metadata
        $attachment = ImagingAttachment::latest()->first();
        expect($attachment)->not->toBeNull()
            ->and($attachment->is_external)->toBeTrue()
            ->and($attachment->external_facility_name)->toBe($facilityName)
            ->and($attachment->external_study_date->format('Y-m-d'))->toBe($studyDate);

        // Clean up for next iteration
        ImagingAttachment::query()->delete();
        LabOrder::query()->delete();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 4: External image metadata
 * Validates: Requirements 1.5, 4.2
 *
 * External image upload should fail if facility name is missing.
 */
test('external image upload fails without facility name', function () {
    // Create shared resources
    $imagingService = LabService::factory()->imaging()->create();
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);
    $doctor->givePermissionTo('investigations.upload-external');
    $doctor->givePermissionTo('consultations.update-own');

    // Run 10 iterations with random data
    for ($i = 0; $i < 10; $i++) {
        $patient = Patient::factory()->create();
        $checkin = PatientCheckin::factory()->create([
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);
        $consultation = Consultation::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'doctor_id' => $doctor->id,
        ]);

        $studyDate = fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $file = UploadedFile::fake()->image('test_image.jpg', 100, 100);

        // Attempt upload without facility name
        $response = $this->actingAs($doctor)->post(
            route('consultation.external-images.store', $consultation),
            [
                'lab_service_id' => $imagingService->id,
                'external_facility_name' => '', // Empty facility name
                'external_study_date' => $studyDate,
                'files' => [$file],
            ]
        );

        // Should fail validation
        $response->assertSessionHasErrors('external_facility_name');

        // No attachment should be created
        expect(ImagingAttachment::count())->toBe(0);
    }
});

/**
 * Feature: imaging-radiology-integration, Property 4: External image metadata
 * Validates: Requirements 1.5, 4.2
 *
 * External image upload should fail if study date is missing.
 */
test('external image upload fails without study date', function () {
    // Create shared resources
    $imagingService = LabService::factory()->imaging()->create();
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);
    $doctor->givePermissionTo('investigations.upload-external');
    $doctor->givePermissionTo('consultations.update-own');

    // Run 10 iterations with random data
    for ($i = 0; $i < 10; $i++) {
        $patient = Patient::factory()->create();
        $checkin = PatientCheckin::factory()->create([
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);
        $consultation = Consultation::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'doctor_id' => $doctor->id,
        ]);

        $facilityName = fake()->company();
        $file = UploadedFile::fake()->image('test_image.jpg', 100, 100);

        // Attempt upload without study date
        $response = $this->actingAs($doctor)->post(
            route('consultation.external-images.store', $consultation),
            [
                'lab_service_id' => $imagingService->id,
                'external_facility_name' => $facilityName,
                'external_study_date' => '', // Empty study date
                'files' => [$file],
            ]
        );

        // Should fail validation
        $response->assertSessionHasErrors('external_study_date');

        // No attachment should be created
        expect(ImagingAttachment::count())->toBe(0);
    }
});

/**
 * Feature: imaging-radiology-integration, Property 4: External image metadata
 * Validates: Requirements 1.5, 4.2
 *
 * External image upload should fail if study date is in the future.
 */
test('external image upload fails with future study date', function () {
    // Create shared resources
    $imagingService = LabService::factory()->imaging()->create();
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);
    $doctor->givePermissionTo('investigations.upload-external');
    $doctor->givePermissionTo('consultations.update-own');

    // Run 10 iterations with random data
    for ($i = 0; $i < 10; $i++) {
        $patient = Patient::factory()->create();
        $checkin = PatientCheckin::factory()->create([
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);
        $consultation = Consultation::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'doctor_id' => $doctor->id,
        ]);

        $facilityName = fake()->company();
        $futureDate = fake()->dateTimeBetween('+1 day', '+1 year')->format('Y-m-d');
        $file = UploadedFile::fake()->image('test_image.jpg', 100, 100);

        // Attempt upload with future study date
        $response = $this->actingAs($doctor)->post(
            route('consultation.external-images.store', $consultation),
            [
                'lab_service_id' => $imagingService->id,
                'external_facility_name' => $facilityName,
                'external_study_date' => $futureDate,
                'files' => [$file],
            ]
        );

        // Should fail validation
        $response->assertSessionHasErrors('external_study_date');

        // No attachment should be created
        expect(ImagingAttachment::count())->toBe(0);
    }
});

/**
 * Feature: imaging-radiology-integration, Property 9: External image non-billable
 * Validates: Requirements 4.5
 *
 * For any external image upload, no billing charge should be created.
 */
test('external images do not create billing charges', function () {
    // Create shared resources
    $imagingService = LabService::factory()->imaging()->create([
        'price' => 150.00, // Set a price to ensure billing would normally occur
    ]);
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);
    $doctor->givePermissionTo('investigations.upload-external');
    $doctor->givePermissionTo('consultations.update-own');

    // Run 10 iterations with random data
    for ($i = 0; $i < 10; $i++) {
        $patient = Patient::factory()->create();
        $checkin = PatientCheckin::factory()->create([
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);
        $consultation = Consultation::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'doctor_id' => $doctor->id,
        ]);

        // Count charges before upload
        $chargeCountBefore = \App\Models\Charge::where('patient_checkin_id', $checkin->id)->count();

        // Generate random external facility name and study date
        $facilityName = fake()->company();
        $studyDate = fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d');

        // Create a fake file
        $file = UploadedFile::fake()->image('test_image.jpg', 100, 100);

        // Upload external image
        $response = $this->actingAs($doctor)->post(
            route('consultation.external-images.store', $consultation),
            [
                'lab_service_id' => $imagingService->id,
                'external_facility_name' => $facilityName,
                'external_study_date' => $studyDate,
                'files' => [$file],
                'descriptions' => ['Test image'],
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Count charges after upload - should be the same (no new charge created)
        $chargeCountAfter = \App\Models\Charge::where('patient_checkin_id', $checkin->id)->count();
        expect($chargeCountAfter)->toBe($chargeCountBefore);

        // Verify the lab order was created but no charge exists for it
        $labOrder = LabOrder::latest()->first();
        expect($labOrder)->not->toBeNull();

        // Verify no charge was created for this lab service
        $charge = \App\Models\Charge::where('patient_checkin_id', $checkin->id)
            ->where('service_code', $imagingService->code)
            ->first();
        expect($charge)->toBeNull();

        // Clean up for next iteration
        ImagingAttachment::query()->delete();
        LabOrder::query()->delete();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 9: External image non-billable
 * Validates: Requirements 4.5
 *
 * External images should be marked with is_external = true.
 */
test('external images are marked as external', function () {
    // Create shared resources
    $imagingService = LabService::factory()->imaging()->create();
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);
    $doctor->givePermissionTo('investigations.upload-external');
    $doctor->givePermissionTo('consultations.update-own');

    // Run 10 iterations with random data
    for ($i = 0; $i < 10; $i++) {
        $patient = Patient::factory()->create();
        $checkin = PatientCheckin::factory()->create([
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);
        $consultation = Consultation::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'doctor_id' => $doctor->id,
        ]);

        // Generate random external facility name and study date
        $facilityName = fake()->company();
        $studyDate = fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d');

        // Create multiple fake files
        $fileCount = fake()->numberBetween(1, 3);
        $files = [];
        for ($j = 0; $j < $fileCount; $j++) {
            $files[] = UploadedFile::fake()->image("test_image_{$j}.jpg", 100, 100);
        }

        // Upload external images
        $response = $this->actingAs($doctor)->post(
            route('consultation.external-images.store', $consultation),
            [
                'lab_service_id' => $imagingService->id,
                'external_facility_name' => $facilityName,
                'external_study_date' => $studyDate,
                'files' => $files,
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify all attachments are marked as external
        $attachments = ImagingAttachment::where('external_facility_name', $facilityName)->get();
        expect($attachments)->toHaveCount($fileCount);

        foreach ($attachments as $attachment) {
            expect($attachment->is_external)->toBeTrue();
        }

        // Clean up for next iteration
        ImagingAttachment::query()->delete();
        LabOrder::query()->delete();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 17: History separation
 * Validates: Requirements 8.1
 *
 * For any patient with both lab tests and imaging studies, the history display
 * should show imaging studies in a separate section from laboratory tests.
 */
test('patient history separates imaging from laboratory tests', function () {
    // Create shared resources
    $labService = LabService::factory()->create(['is_imaging' => false]);
    $imagingService = LabService::factory()->imaging()->create();
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);
    $doctor->givePermissionTo('consultations.view-own');

    // Run 10 iterations with random data
    for ($i = 0; $i < 10; $i++) {
        $patient = Patient::factory()->create();

        // Create a previous consultation with both lab and imaging orders
        $previousCheckin = PatientCheckin::factory()->create([
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);
        $previousConsultation = Consultation::factory()->create([
            'patient_checkin_id' => $previousCheckin->id,
            'doctor_id' => $doctor->id,
            'status' => 'completed',
        ]);

        // Create lab orders (non-imaging)
        $labOrderCount = fake()->numberBetween(1, 3);
        for ($j = 0; $j < $labOrderCount; $j++) {
            LabOrder::factory()->create([
                'lab_service_id' => $labService->id,
                'orderable_type' => Consultation::class,
                'orderable_id' => $previousConsultation->id,
                'consultation_id' => $previousConsultation->id,
                'ordered_by' => $doctor->id,
                'status' => 'completed',
            ]);
        }

        // Create imaging orders
        $imagingOrderCount = fake()->numberBetween(1, 3);
        for ($j = 0; $j < $imagingOrderCount; $j++) {
            LabOrder::factory()->create([
                'lab_service_id' => $imagingService->id,
                'orderable_type' => Consultation::class,
                'orderable_id' => $previousConsultation->id,
                'consultation_id' => $previousConsultation->id,
                'ordered_by' => $doctor->id,
                'status' => 'completed',
            ]);
        }

        // Create current consultation
        $currentCheckin = PatientCheckin::factory()->create([
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);
        $currentConsultation = Consultation::factory()->create([
            'patient_checkin_id' => $currentCheckin->id,
            'doctor_id' => $doctor->id,
            'status' => 'in_progress',
        ]);

        // Access the consultation show page
        $response = $this->actingAs($doctor)->get(route('consultation.show', $currentConsultation));
        $response->assertOk();

        // Get the patient history from the response
        $patientHistory = $response->original->getData()['page']['props']['patientHistory'];

        // Verify previousConsultations contains only lab orders (non-imaging)
        foreach ($patientHistory['previousConsultations'] as $consultation) {
            foreach ($consultation['lab_orders'] as $order) {
                expect($order['lab_service']['is_imaging'])->toBeFalse();
            }
        }

        // Verify previousImagingStudies contains only imaging orders
        foreach ($patientHistory['previousImagingStudies'] as $imagingStudy) {
            expect($imagingStudy['lab_service']['is_imaging'])->toBeTrue();
        }

        // Verify counts match
        $labOrdersInHistory = collect($patientHistory['previousConsultations'])
            ->flatMap(fn ($c) => $c['lab_orders'])
            ->count();
        $imagingOrdersInHistory = count($patientHistory['previousImagingStudies']);

        expect($labOrdersInHistory)->toBe($labOrderCount);
        expect($imagingOrdersInHistory)->toBe($imagingOrderCount);

        // Clean up for next iteration
        LabOrder::query()->delete();
        Consultation::query()->delete();
        PatientCheckin::query()->delete();
        Patient::query()->delete();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 18: History completeness
 * Validates: Requirements 8.4
 *
 * For any patient, the imaging history should include both internal and external imaging studies.
 */
test('patient imaging history includes both internal and external studies', function () {
    // Create shared resources
    $imagingService = LabService::factory()->imaging()->create();
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);
    $doctor->givePermissionTo('consultations.view-own');
    $doctor->givePermissionTo('consultations.update-own');
    $doctor->givePermissionTo('investigations.upload-external');

    // Run 10 iterations with random data
    for ($i = 0; $i < 10; $i++) {
        $patient = Patient::factory()->create();

        // Create a previous consultation with internal imaging order
        $previousCheckin = PatientCheckin::factory()->create([
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);
        $previousConsultation = Consultation::factory()->create([
            'patient_checkin_id' => $previousCheckin->id,
            'doctor_id' => $doctor->id,
            'status' => 'completed',
        ]);

        // Create internal imaging order
        $internalOrder = LabOrder::factory()->create([
            'lab_service_id' => $imagingService->id,
            'orderable_type' => Consultation::class,
            'orderable_id' => $previousConsultation->id,
            'consultation_id' => $previousConsultation->id,
            'ordered_by' => $doctor->id,
            'status' => 'completed',
        ]);

        // Create internal attachment
        ImagingAttachment::factory()->create([
            'lab_order_id' => $internalOrder->id,
            'is_external' => false,
            'uploaded_by' => $doctor->id,
        ]);

        // Create external imaging order
        $externalOrder = LabOrder::factory()->create([
            'lab_service_id' => $imagingService->id,
            'orderable_type' => Consultation::class,
            'orderable_id' => $previousConsultation->id,
            'consultation_id' => $previousConsultation->id,
            'ordered_by' => $doctor->id,
            'status' => 'completed',
        ]);

        // Create external attachment
        ImagingAttachment::factory()->create([
            'lab_order_id' => $externalOrder->id,
            'is_external' => true,
            'external_facility_name' => fake()->company(),
            'external_study_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'uploaded_by' => $doctor->id,
        ]);

        // Create current consultation
        $currentCheckin = PatientCheckin::factory()->create([
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);
        $currentConsultation = Consultation::factory()->create([
            'patient_checkin_id' => $currentCheckin->id,
            'doctor_id' => $doctor->id,
            'status' => 'in_progress',
        ]);

        // Access the consultation show page
        $response = $this->actingAs($doctor)->get(route('consultation.show', $currentConsultation));
        $response->assertOk();

        // Get the patient history from the response
        $patientHistory = $response->original->getData()['page']['props']['patientHistory'];

        // Verify both internal and external imaging studies are included
        $imagingStudies = collect($patientHistory['previousImagingStudies']);
        expect($imagingStudies)->toHaveCount(2);

        // Verify we have both internal and external
        $hasInternal = $imagingStudies->contains(fn ($study) => ! $study['is_external']);
        $hasExternal = $imagingStudies->contains(fn ($study) => $study['is_external']);

        expect($hasInternal)->toBeTrue();
        expect($hasExternal)->toBeTrue();

        // Clean up for next iteration
        ImagingAttachment::query()->delete();
        LabOrder::query()->delete();
        Consultation::query()->delete();
        PatientCheckin::query()->delete();
        Patient::query()->delete();
    }
});
