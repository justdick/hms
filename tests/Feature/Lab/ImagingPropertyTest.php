<?php

/**
 * Property-based tests for Imaging/Radiology Integration
 * Feature: imaging-radiology-integration
 */

use App\Models\LabService;
use App\Services\ImagingStorageService;
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

/**
 * Feature: imaging-radiology-integration, Property 15: File storage path structure
 * Validates: Requirements 6.5
 *
 * For any uploaded image, the file should be stored in a path matching the pattern:
 * imaging/{year}/{month}/{patient_id}/{lab_order_id}/original/{filename}.
 */
test('file storage path follows correct structure', function () {
    $service = new ImagingStorageService;

    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
        // Create a lab order with full relationship chain
        $patient = \App\Models\Patient::factory()->create();
        $checkin = \App\Models\PatientCheckin::factory()->create([
            'patient_id' => $patient->id,
        ]);
        $consultation = \App\Models\Consultation::factory()->create([
            'patient_checkin_id' => $checkin->id,
        ]);
        $labOrder = \App\Models\LabOrder::factory()->create([
            'consultation_id' => $consultation->id,
            'orderable_type' => \App\Models\Consultation::class,
            'orderable_id' => $consultation->id,
        ]);

        // Get the storage path
        $storagePath = $service->getStoragePath($labOrder);

        // Verify path structure: imaging/{year}/{month}/{patient_id}/{lab_order_id}/original
        $year = now()->format('Y');
        $month = now()->format('m');

        $expectedPattern = sprintf(
            'imaging/%s/%s/%d/%d/original',
            $year,
            $month,
            $patient->id,
            $labOrder->id
        );

        expect($storagePath)->toBe($expectedPattern);

        // Also verify the path components individually
        $pathParts = explode('/', $storagePath);
        expect($pathParts[0])->toBe('imaging')
            ->and($pathParts[1])->toBe($year)
            ->and($pathParts[2])->toBe($month)
            ->and((int) $pathParts[3])->toBe($patient->id)
            ->and((int) $pathParts[4])->toBe($labOrder->id)
            ->and($pathParts[5])->toBe('original');
    }
});

/**
 * Feature: imaging-radiology-integration, Property 15: File storage path structure
 * Validates: Requirements 6.5
 *
 * For any lab order from a ward round, the storage path should still follow the correct structure.
 */
test('file storage path follows correct structure for ward round orders', function () {
    $service = new ImagingStorageService;

    // Create shared resources to reuse across iterations
    $ward = \App\Models\Ward::factory()->create();
    $department = \App\Models\Department::factory()->create();
    $labService = \App\Models\LabService::factory()->create();
    $doctor = \App\Models\User::factory()->create();

    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
        // Create a lab order from a ward round with explicit relationships
        $patient = \App\Models\Patient::factory()->create();
        $bed = \App\Models\Bed::create([
            'ward_id' => $ward->id,
            'bed_number' => sprintf('B%03d-%d', $i + 1, fake()->numberBetween(1, 999)),
            'status' => 'occupied',
            'type' => 'standard',
            'is_active' => true,
        ]);

        // Create checkin and consultation with the shared department
        $checkin = \App\Models\PatientCheckin::factory()->create([
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);
        $consultation = \App\Models\Consultation::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'doctor_id' => $doctor->id,
        ]);

        $admission = \App\Models\PatientAdmission::factory()->create([
            'patient_id' => $patient->id,
            'consultation_id' => $consultation->id,
            'ward_id' => $ward->id,
            'bed_id' => $bed->id,
        ]);
        $wardRound = \App\Models\WardRound::factory()->create([
            'patient_admission_id' => $admission->id,
            'doctor_id' => $doctor->id,
        ]);
        $labOrder = \App\Models\LabOrder::factory()->create([
            'consultation_id' => $consultation->id,
            'orderable_type' => \App\Models\WardRound::class,
            'orderable_id' => $wardRound->id,
            'lab_service_id' => $labService->id,
            'ordered_by' => $doctor->id,
        ]);

        // Get the storage path
        $storagePath = $service->getStoragePath($labOrder);

        // Verify path structure: imaging/{year}/{month}/{patient_id}/{lab_order_id}/original
        $year = now()->format('Y');
        $month = now()->format('m');

        $expectedPattern = sprintf(
            'imaging/%s/%s/%d/%d/original',
            $year,
            $month,
            $patient->id,
            $labOrder->id
        );

        expect($storagePath)->toBe($expectedPattern);
    }
});

/**
 * Feature: imaging-radiology-integration, Property 8: File type validation
 * Validates: Requirements 4.3
 *
 * For any file upload attempt, the system should accept only JPEG, PNG, and PDF files,
 * and reject all other file types.
 */
test('valid file types are accepted', function () {
    $service = new ImagingStorageService;

    // Valid MIME types and extensions
    $validCombinations = [
        ['mime' => 'image/jpeg', 'extension' => 'jpg'],
        ['mime' => 'image/jpeg', 'extension' => 'jpeg'],
        ['mime' => 'image/png', 'extension' => 'png'],
        ['mime' => 'application/pdf', 'extension' => 'pdf'],
    ];

    // Run 100 iterations with random valid file types
    for ($i = 0; $i < 100; $i++) {
        $combo = fake()->randomElement($validCombinations);

        // Create a mock uploaded file with valid type
        $file = \Illuminate\Http\UploadedFile::fake()->create(
            'test_image_'.$i.'.'.$combo['extension'],
            1024, // 1KB
            $combo['mime']
        );

        expect($service->isValidFileType($file))->toBeTrue();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 8: File type validation
 * Validates: Requirements 4.3
 *
 * For any file upload attempt with invalid file types, the system should reject them.
 */
test('invalid file types are rejected', function () {
    $service = new ImagingStorageService;

    // Invalid MIME types and extensions
    $invalidCombinations = [
        ['mime' => 'image/gif', 'extension' => 'gif'],
        ['mime' => 'image/webp', 'extension' => 'webp'],
        ['mime' => 'image/bmp', 'extension' => 'bmp'],
        ['mime' => 'image/tiff', 'extension' => 'tiff'],
        ['mime' => 'application/msword', 'extension' => 'doc'],
        ['mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'extension' => 'docx'],
        ['mime' => 'application/vnd.ms-excel', 'extension' => 'xls'],
        ['mime' => 'text/plain', 'extension' => 'txt'],
        ['mime' => 'text/html', 'extension' => 'html'],
        ['mime' => 'application/zip', 'extension' => 'zip'],
        ['mime' => 'video/mp4', 'extension' => 'mp4'],
        ['mime' => 'audio/mpeg', 'extension' => 'mp3'],
    ];

    // Run 100 iterations with random invalid file types
    for ($i = 0; $i < 100; $i++) {
        $combo = fake()->randomElement($invalidCombinations);

        // Create a mock uploaded file with invalid type
        $file = \Illuminate\Http\UploadedFile::fake()->create(
            'test_file_'.$i.'.'.$combo['extension'],
            1024, // 1KB
            $combo['mime']
        );

        expect($service->isValidFileType($file))->toBeFalse();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 8: File type validation
 * Validates: Requirements 4.3
 *
 * For any file upload attempt, files within the 50MB limit should be accepted.
 */
test('valid file sizes are accepted', function () {
    $service = new ImagingStorageService;

    // Run 100 iterations with random valid file sizes (up to 50MB)
    for ($i = 0; $i < 100; $i++) {
        // Random size between 1KB and 50MB (50 * 1024 KB)
        $sizeInKb = fake()->numberBetween(1, 50 * 1024);

        $file = \Illuminate\Http\UploadedFile::fake()->create(
            'test_image_'.$i.'.jpg',
            $sizeInKb,
            'image/jpeg'
        );

        expect($service->isValidFileSize($file))->toBeTrue();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 8: File type validation
 * Validates: Requirements 4.3
 *
 * For any file upload attempt, files exceeding the 50MB limit should be rejected.
 */
test('oversized files are rejected', function () {
    $service = new ImagingStorageService;

    // Run 100 iterations with random oversized files (over 50MB)
    for ($i = 0; $i < 100; $i++) {
        // Random size between 51MB and 100MB
        $sizeInKb = fake()->numberBetween(51 * 1024, 100 * 1024);

        $file = \Illuminate\Http\UploadedFile::fake()->create(
            'test_image_'.$i.'.jpg',
            $sizeInKb,
            'image/jpeg'
        );

        expect($service->isValidFileSize($file))->toBeFalse();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 8: File type validation
 * Validates: Requirements 4.3
 *
 * For any valid file, the validateFile method should return an empty array.
 */
test('validateFile returns empty array for valid files', function () {
    $service = new ImagingStorageService;

    $validCombinations = [
        ['mime' => 'image/jpeg', 'extension' => 'jpg'],
        ['mime' => 'image/jpeg', 'extension' => 'jpeg'],
        ['mime' => 'image/png', 'extension' => 'png'],
        ['mime' => 'application/pdf', 'extension' => 'pdf'],
    ];

    // Run 100 iterations with random valid files
    for ($i = 0; $i < 100; $i++) {
        $combo = fake()->randomElement($validCombinations);
        $sizeInKb = fake()->numberBetween(1, 50 * 1024); // Up to 50MB

        $file = \Illuminate\Http\UploadedFile::fake()->create(
            'test_image_'.$i.'.'.$combo['extension'],
            $sizeInKb,
            $combo['mime']
        );

        $errors = $service->validateFile($file);

        expect($errors)->toBeArray()->toBeEmpty();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 8: File type validation
 * Validates: Requirements 4.3
 *
 * For any invalid file, the validateFile method should return appropriate error messages.
 */
test('validateFile returns errors for invalid files', function () {
    $service = new ImagingStorageService;

    // Test invalid file type
    $invalidTypeFile = \Illuminate\Http\UploadedFile::fake()->create(
        'test_file.gif',
        1024,
        'image/gif'
    );

    $errors = $service->validateFile($invalidTypeFile);
    expect($errors)->toBeArray()
        ->not->toBeEmpty()
        ->toContain('Only JPEG, PNG, and PDF files are allowed.');

    // Test oversized file
    $oversizedFile = \Illuminate\Http\UploadedFile::fake()->create(
        'test_image.jpg',
        51 * 1024, // 51MB
        'image/jpeg'
    );

    $errors = $service->validateFile($oversizedFile);
    expect($errors)->toBeArray()
        ->not->toBeEmpty()
        ->toContain('File size exceeds 50MB limit.');

    // Test both invalid type and oversized
    $invalidBothFile = \Illuminate\Http\UploadedFile::fake()->create(
        'test_file.gif',
        51 * 1024, // 51MB
        'image/gif'
    );

    $errors = $service->validateFile($invalidBothFile);
    expect($errors)->toBeArray()
        ->toHaveCount(2)
        ->toContain('Only JPEG, PNG, and PDF files are allowed.')
        ->toContain('File size exceeds 50MB limit.');
});

/**
 * Feature: imaging-radiology-integration, Property 19: Authorization - order imaging
 * Validates: Requirements 9.1
 *
 * For any user without the `investigations.order` permission, attempting to order
 * an imaging study should be rejected with a 403 status.
 */
test('users without investigations.order permission cannot order imaging studies', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Run 100 iterations with random users without the permission
    for ($i = 0; $i < 100; $i++) {
        // Create a user without the investigations.order permission
        $user = \App\Models\User::factory()->create();

        // Ensure user does NOT have the permission
        expect($user->can('investigations.order'))->toBeFalse();

        // Create a consultation to order imaging for
        $department = \App\Models\Department::factory()->create();
        $user->departments()->attach($department->id);

        $checkin = \App\Models\PatientCheckin::factory()->create([
            'department_id' => $department->id,
        ]);
        $consultation = \App\Models\Consultation::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'doctor_id' => $user->id,
        ]);

        // Create an imaging service
        $imagingService = \App\Models\LabService::factory()->imaging()->create();

        // Attempt to order imaging study - should be forbidden
        $response = $this->actingAs($user)->post(route('consultation.lab-orders.store', $consultation), [
            'lab_service_id' => $imagingService->id,
            'priority' => 'routine',
            'clinical_indication' => 'Test indication',
        ]);

        $response->assertForbidden();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 19: Authorization - order imaging
 * Validates: Requirements 9.1
 *
 * For any user with the `investigations.order` permission, ordering an imaging study
 * should be allowed.
 */
test('users with investigations.order permission can order imaging studies', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Run 100 iterations with random users with the permission
    for ($i = 0; $i < 100; $i++) {
        // Create a user with the investigations.order permission
        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo('investigations.order');
        $user->givePermissionTo('lab-orders.create');

        // Create a consultation to order imaging for
        $department = \App\Models\Department::factory()->create();
        $user->departments()->attach($department->id);

        $checkin = \App\Models\PatientCheckin::factory()->create([
            'department_id' => $department->id,
        ]);
        $consultation = \App\Models\Consultation::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'doctor_id' => $user->id,
        ]);

        // Create an imaging service
        $imagingService = \App\Models\LabService::factory()->imaging()->create();

        // Attempt to order imaging study - should be allowed
        $response = $this->actingAs($user)->post(route('consultation.lab-orders.store', $consultation), [
            'lab_service_id' => $imagingService->id,
            'priority' => 'routine',
            'clinical_indication' => 'Test indication',
        ]);

        // Should not be forbidden (could be redirect or success)
        expect($response->status())->not->toBe(403);
    }
});

/**
 * Feature: imaging-radiology-integration, Property 20: Authorization - upload images
 * Validates: Requirements 9.2
 *
 * For any user without the `radiology.upload` permission, attempting to upload images
 * in radiology should be rejected with a 403 status.
 */
test('users without radiology.upload permission cannot upload images', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $radiologyPolicy = new \App\Policies\RadiologyPolicy;

    // Create shared resources to reuse across iterations
    $imagingService = \App\Models\LabService::factory()->imaging()->create();
    $consultation = \App\Models\Consultation::factory()->create();

    // Run 100 iterations with random users without the permission
    for ($i = 0; $i < 100; $i++) {
        // Create a user without the radiology.upload permission
        $user = \App\Models\User::factory()->create();

        // Ensure user does NOT have the permission and is not admin
        expect($user->can('radiology.upload'))->toBeFalse()
            ->and($user->hasRole('Admin'))->toBeFalse();

        // Create an imaging order using shared resources
        $labOrder = \App\Models\LabOrder::factory()->create([
            'lab_service_id' => $imagingService->id,
            'consultation_id' => $consultation->id,
        ]);

        // Check policy - should deny
        expect($radiologyPolicy->uploadImages($user, $labOrder))->toBeFalse();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 20: Authorization - upload images
 * Validates: Requirements 9.2
 *
 * For any user with the `radiology.upload` permission, uploading images should be allowed.
 */
test('users with radiology.upload permission can upload images', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $radiologyPolicy = new \App\Policies\RadiologyPolicy;

    // Create shared resources to reuse across iterations
    $imagingService = \App\Models\LabService::factory()->imaging()->create();
    $consultation = \App\Models\Consultation::factory()->create();

    // Run 100 iterations with random users with the permission
    for ($i = 0; $i < 100; $i++) {
        // Create a user with the radiology.upload permission
        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo('radiology.upload');

        // Create an imaging order using shared resources
        $labOrder = \App\Models\LabOrder::factory()->create([
            'lab_service_id' => $imagingService->id,
            'consultation_id' => $consultation->id,
        ]);

        // Check policy - should allow
        expect($radiologyPolicy->uploadImages($user, $labOrder))->toBeTrue();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 21: Authorization - enter report
 * Validates: Requirements 9.3
 *
 * For any user without the `radiology.report` permission, attempting to enter a radiology
 * report should be rejected with a 403 status.
 */
test('users without radiology.report permission cannot enter reports', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $radiologyPolicy = new \App\Policies\RadiologyPolicy;

    // Create shared resources to reuse across iterations
    $imagingService = \App\Models\LabService::factory()->imaging()->create();
    $consultation = \App\Models\Consultation::factory()->create();

    // Run 100 iterations with random users without the permission
    for ($i = 0; $i < 100; $i++) {
        // Create a user without the radiology.report permission
        $user = \App\Models\User::factory()->create();

        // Ensure user does NOT have the permission and is not admin
        expect($user->can('radiology.report'))->toBeFalse()
            ->and($user->hasRole('Admin'))->toBeFalse();

        // Create an imaging order using shared resources
        $labOrder = \App\Models\LabOrder::factory()->create([
            'lab_service_id' => $imagingService->id,
            'consultation_id' => $consultation->id,
        ]);

        // Check policy - should deny
        expect($radiologyPolicy->enterReport($user, $labOrder))->toBeFalse();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 21: Authorization - enter report
 * Validates: Requirements 9.3
 *
 * For any user with the `radiology.report` permission, entering a radiology report
 * should be allowed.
 */
test('users with radiology.report permission can enter reports', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $radiologyPolicy = new \App\Policies\RadiologyPolicy;

    // Create shared resources to reuse across iterations
    $imagingService = \App\Models\LabService::factory()->imaging()->create();
    $consultation = \App\Models\Consultation::factory()->create();

    // Run 100 iterations with random users with the permission
    for ($i = 0; $i < 100; $i++) {
        // Create a user with the radiology.report permission
        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo('radiology.report');

        // Create an imaging order using shared resources
        $labOrder = \App\Models\LabOrder::factory()->create([
            'lab_service_id' => $imagingService->id,
            'consultation_id' => $consultation->id,
        ]);

        // Check policy - should allow
        expect($radiologyPolicy->enterReport($user, $labOrder))->toBeTrue();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 22: Authorization - external upload
 * Validates: Requirements 9.4
 *
 * For any user without the `investigations.upload-external` permission, attempting to
 * upload external images should be rejected with a 403 status.
 */
test('users without investigations.upload-external permission cannot upload external images', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $labOrderPolicy = new \App\Policies\LabOrderPolicy;

    // Create shared resources to reuse across iterations
    $imagingService = \App\Models\LabService::factory()->imaging()->create();
    $consultation = \App\Models\Consultation::factory()->create();

    // Run 100 iterations with random users without the permission
    for ($i = 0; $i < 100; $i++) {
        // Create a user without the investigations.upload-external permission
        $user = \App\Models\User::factory()->create();

        // Ensure user does NOT have the permission and is not admin
        expect($user->can('investigations.upload-external'))->toBeFalse()
            ->and($user->hasRole('Admin'))->toBeFalse();

        // Create an imaging order using shared resources
        $labOrder = \App\Models\LabOrder::factory()->create([
            'lab_service_id' => $imagingService->id,
            'consultation_id' => $consultation->id,
        ]);

        // Check policy - should deny
        expect($labOrderPolicy->uploadExternalImages($user, $labOrder))->toBeFalse();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 22: Authorization - external upload
 * Validates: Requirements 9.4
 *
 * For any user with the `investigations.upload-external` permission, uploading external
 * images should be allowed.
 */
test('users with investigations.upload-external permission can upload external images', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $labOrderPolicy = new \App\Policies\LabOrderPolicy;

    // Create shared resources to reuse across iterations
    $imagingService = \App\Models\LabService::factory()->imaging()->create();
    $consultation = \App\Models\Consultation::factory()->create();

    // Run 100 iterations with random users with the permission
    for ($i = 0; $i < 100; $i++) {
        // Create a user with the investigations.upload-external permission
        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo('investigations.upload-external');

        // Create an imaging order using shared resources
        $labOrder = \App\Models\LabOrder::factory()->create([
            'lab_service_id' => $imagingService->id,
            'consultation_id' => $consultation->id,
        ]);

        // Check policy - should allow
        expect($labOrderPolicy->uploadExternalImages($user, $labOrder))->toBeTrue();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 23: Authorization - view worklist
 * Validates: Requirements 9.5
 *
 * For any user without the `radiology.view-worklist` permission, attempting to access
 * the radiology worklist should be rejected with a 403 status.
 */
test('users without radiology.view-worklist permission cannot view worklist', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $radiologyPolicy = new \App\Policies\RadiologyPolicy;

    // Run 100 iterations with random users without the permission
    for ($i = 0; $i < 100; $i++) {
        // Create a user without the radiology.view-worklist permission
        $user = \App\Models\User::factory()->create();

        // Ensure user does NOT have the permission and is not admin
        expect($user->can('radiology.view-worklist'))->toBeFalse()
            ->and($user->hasRole('Admin'))->toBeFalse();

        // Check policy - should deny
        expect($radiologyPolicy->viewWorklist($user))->toBeFalse();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 23: Authorization - view worklist
 * Validates: Requirements 9.5
 *
 * For any user with the `radiology.view-worklist` permission, viewing the radiology
 * worklist should be allowed.
 */
test('users with radiology.view-worklist permission can view worklist', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $radiologyPolicy = new \App\Policies\RadiologyPolicy;

    // Run 100 iterations with random users with the permission
    for ($i = 0; $i < 100; $i++) {
        // Create a user with the radiology.view-worklist permission
        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo('radiology.view-worklist');

        // Check policy - should allow
        expect($radiologyPolicy->viewWorklist($user))->toBeTrue();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 20: Authorization - upload images
 * Validates: Requirements 9.2
 *
 * Admin users should always be able to upload images regardless of specific permissions.
 */
test('admin users can always upload images', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $radiologyPolicy = new \App\Policies\RadiologyPolicy;

    // Create shared resources to reuse across iterations
    $imagingService = \App\Models\LabService::factory()->imaging()->create();
    $consultation = \App\Models\Consultation::factory()->create();

    // Run 100 iterations with admin users
    for ($i = 0; $i < 100; $i++) {
        // Create an admin user
        $user = \App\Models\User::factory()->create();
        $user->assignRole('Admin');

        // Create an imaging order using shared resources
        $labOrder = \App\Models\LabOrder::factory()->create([
            'lab_service_id' => $imagingService->id,
            'consultation_id' => $consultation->id,
        ]);

        // Check policy - should allow for admin
        expect($radiologyPolicy->uploadImages($user, $labOrder))->toBeTrue();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 21: Authorization - enter report
 * Validates: Requirements 9.3
 *
 * Admin users should always be able to enter reports regardless of specific permissions.
 */
test('admin users can always enter reports', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $radiologyPolicy = new \App\Policies\RadiologyPolicy;

    // Create shared resources to reuse across iterations
    $imagingService = \App\Models\LabService::factory()->imaging()->create();
    $consultation = \App\Models\Consultation::factory()->create();

    // Run 100 iterations with admin users
    for ($i = 0; $i < 100; $i++) {
        // Create an admin user
        $user = \App\Models\User::factory()->create();
        $user->assignRole('Admin');

        // Create an imaging order using shared resources
        $labOrder = \App\Models\LabOrder::factory()->create([
            'lab_service_id' => $imagingService->id,
            'consultation_id' => $consultation->id,
        ]);

        // Check policy - should allow for admin
        expect($radiologyPolicy->enterReport($user, $labOrder))->toBeTrue();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 22: Authorization - external upload
 * Validates: Requirements 9.4
 *
 * Admin users should always be able to upload external images regardless of specific permissions.
 */
test('admin users can always upload external images', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $labOrderPolicy = new \App\Policies\LabOrderPolicy;

    // Create shared resources to reuse across iterations
    $imagingService = \App\Models\LabService::factory()->imaging()->create();
    $consultation = \App\Models\Consultation::factory()->create();

    // Run 100 iterations with admin users
    for ($i = 0; $i < 100; $i++) {
        // Create an admin user
        $user = \App\Models\User::factory()->create();
        $user->assignRole('Admin');

        // Create an imaging order using shared resources
        $labOrder = \App\Models\LabOrder::factory()->create([
            'lab_service_id' => $imagingService->id,
            'consultation_id' => $consultation->id,
        ]);

        // Check policy - should allow for admin
        expect($labOrderPolicy->uploadExternalImages($user, $labOrder))->toBeTrue();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 23: Authorization - view worklist
 * Validates: Requirements 9.5
 *
 * Admin users should always be able to view the worklist regardless of specific permissions.
 */
test('admin users can always view worklist', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $radiologyPolicy = new \App\Policies\RadiologyPolicy;

    // Run 100 iterations with admin users
    for ($i = 0; $i < 100; $i++) {
        // Create an admin user
        $user = \App\Models\User::factory()->create();
        $user->assignRole('Admin');

        // Check policy - should allow for admin
        expect($radiologyPolicy->viewWorklist($user))->toBeTrue();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 20: Authorization - upload images
 * Validates: Requirements 9.2
 *
 * Users with radiology.upload permission should be denied if the order is not an imaging order.
 */
test('upload images denied for non-imaging orders even with permission', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $radiologyPolicy = new \App\Policies\RadiologyPolicy;

    // Create shared resources to reuse across iterations
    $labService = \App\Models\LabService::factory()->laboratory()->create();
    $consultation = \App\Models\Consultation::factory()->create();

    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a user with the radiology.upload permission
        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo('radiology.upload');

        // Create a non-imaging (laboratory) order using shared resources
        $labOrder = \App\Models\LabOrder::factory()->create([
            'lab_service_id' => $labService->id,
            'consultation_id' => $consultation->id,
        ]);

        // Check policy - should deny because it's not an imaging order
        expect($radiologyPolicy->uploadImages($user, $labOrder))->toBeFalse();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 22: Authorization - external upload
 * Validates: Requirements 9.4
 *
 * Users with investigations.upload-external permission should be denied if the order is not an imaging order.
 */
test('external upload denied for non-imaging orders even with permission', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $labOrderPolicy = new \App\Policies\LabOrderPolicy;

    // Create shared resources to reuse across iterations
    $labService = \App\Models\LabService::factory()->laboratory()->create();
    $consultation = \App\Models\Consultation::factory()->create();

    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a user with the investigations.upload-external permission
        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo('investigations.upload-external');

        // Create a non-imaging (laboratory) order using shared resources
        $labOrder = \App\Models\LabOrder::factory()->create([
            'lab_service_id' => $labService->id,
            'consultation_id' => $consultation->id,
        ]);

        // Check policy - should deny because it's not an imaging order
        expect($labOrderPolicy->uploadExternalImages($user, $labOrder))->toBeFalse();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 5: Permission-based menu visibility
 * Validates: Requirements 2.4, 2.5
 *
 * For any user, the Laboratory sub-item should be visible if and only if the user has
 * laboratory permissions (lab-orders.view-all or lab-orders.view-dept), and the Radiology
 * sub-item should be visible if and only if the user has radiology.view-worklist permission.
 */
test('investigations permissions are correctly shared based on user permissions', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Run 10 iterations with random permission combinations
    for ($i = 0; $i < 10; $i++) {
        $user = \App\Models\User::factory()->create();

        // Randomly assign permissions
        $hasLabViewAll = fake()->boolean();
        $hasLabViewDept = fake()->boolean();
        $hasRadiologyWorklist = fake()->boolean();

        if ($hasLabViewAll) {
            $user->givePermissionTo('lab-orders.view-all');
        }
        if ($hasLabViewDept) {
            $user->givePermissionTo('lab-orders.view-dept');
        }
        if ($hasRadiologyWorklist) {
            $user->givePermissionTo('radiology.view-worklist');
        }

        // Make a request to get the shared data
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();

        $props = $response->viewData('page')['props'];

        // Verify Laboratory visibility: should be true if user has lab-orders.view-all OR lab-orders.view-dept
        $expectedLabVisibility = $hasLabViewAll || $hasLabViewDept;
        expect($props['auth']['permissions']['investigations']['viewLab'])->toBe($expectedLabVisibility);

        // Verify Radiology visibility: should be true if user has radiology.view-worklist
        expect($props['auth']['permissions']['investigations']['viewRadiology'])->toBe($hasRadiologyWorklist);
    }
});

/**
 * Feature: imaging-radiology-integration, Property 5: Permission-based menu visibility
 * Validates: Requirements 2.4, 2.5
 *
 * For any user with only lab-orders.view-all permission, only Laboratory should be visible.
 */
test('user with only lab-orders.view-all sees only Laboratory', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Run 10 iterations
    for ($i = 0; $i < 10; $i++) {
        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo('lab-orders.view-all');

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();

        $props = $response->viewData('page')['props'];

        expect($props['auth']['permissions']['investigations']['viewLab'])->toBeTrue();
        expect($props['auth']['permissions']['investigations']['viewRadiology'])->toBeFalse();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 5: Permission-based menu visibility
 * Validates: Requirements 2.4, 2.5
 *
 * For any user with only lab-orders.view-dept permission, only Laboratory should be visible.
 */
test('user with only lab-orders.view-dept sees only Laboratory', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Run 10 iterations
    for ($i = 0; $i < 10; $i++) {
        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo('lab-orders.view-dept');

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();

        $props = $response->viewData('page')['props'];

        expect($props['auth']['permissions']['investigations']['viewLab'])->toBeTrue();
        expect($props['auth']['permissions']['investigations']['viewRadiology'])->toBeFalse();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 5: Permission-based menu visibility
 * Validates: Requirements 2.4, 2.5
 *
 * For any user with only radiology.view-worklist permission, only Radiology should be visible.
 */
test('user with only radiology.view-worklist sees only Radiology', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Run 10 iterations
    for ($i = 0; $i < 10; $i++) {
        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo('radiology.view-worklist');

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();

        $props = $response->viewData('page')['props'];

        expect($props['auth']['permissions']['investigations']['viewLab'])->toBeFalse();
        expect($props['auth']['permissions']['investigations']['viewRadiology'])->toBeTrue();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 5: Permission-based menu visibility
 * Validates: Requirements 2.4, 2.5
 *
 * For any user with both lab and radiology permissions, both should be visible.
 */
test('user with both lab and radiology permissions sees both', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Run 10 iterations
    for ($i = 0; $i < 10; $i++) {
        $user = \App\Models\User::factory()->create();
        $user->givePermissionTo('lab-orders.view-all');
        $user->givePermissionTo('radiology.view-worklist');

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();

        $props = $response->viewData('page')['props'];

        expect($props['auth']['permissions']['investigations']['viewLab'])->toBeTrue();
        expect($props['auth']['permissions']['investigations']['viewRadiology'])->toBeTrue();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 5: Permission-based menu visibility
 * Validates: Requirements 2.4, 2.5
 *
 * For any user with no lab or radiology permissions, neither should be visible.
 */
test('user with no lab or radiology permissions sees neither', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Run 10 iterations
    for ($i = 0; $i < 10; $i++) {
        $user = \App\Models\User::factory()->create();
        // Don't give any lab or radiology permissions

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();

        $props = $response->viewData('page')['props'];

        expect($props['auth']['permissions']['investigations']['viewLab'])->toBeFalse();
        expect($props['auth']['permissions']['investigations']['viewRadiology'])->toBeFalse();
    }
});
