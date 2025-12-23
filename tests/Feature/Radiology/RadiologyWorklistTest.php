<?php

/**
 * Property-based tests for Radiology Worklist
 * Feature: imaging-radiology-integration
 */

use App\Models\Consultation;
use App\Models\LabOrder;
use App\Models\LabService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Feature: imaging-radiology-integration, Property 10: Worklist sorting
 * Validates: Requirements 5.1
 *
 * For any set of pending imaging orders, the worklist should display them sorted
 * by priority (STAT > urgent > routine) and then by order time (oldest first).
 */
test('worklist orders are sorted by priority then by order time', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Create shared resources to reuse across iterations
    $imagingService = LabService::factory()->imaging()->create();
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);

    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
        // Clear existing lab orders
        LabOrder::query()->delete();

        // Create random number of imaging orders with different priorities and times
        $orderCount = fake()->numberBetween(5, 15);

        for ($j = 0; $j < $orderCount; $j++) {
            $patient = \App\Models\Patient::factory()->create();
            $checkin = \App\Models\PatientCheckin::factory()->create([
                'patient_id' => $patient->id,
                'department_id' => $department->id,
            ]);
            $consultation = Consultation::factory()->create([
                'patient_checkin_id' => $checkin->id,
                'doctor_id' => $doctor->id,
            ]);
            $priority = fake()->randomElement(['stat', 'urgent', 'routine']);
            $orderedAt = now()->subMinutes(fake()->numberBetween(1, 1000));

            LabOrder::factory()->create([
                'lab_service_id' => $imagingService->id,
                'consultation_id' => $consultation->id,
                'orderable_type' => Consultation::class,
                'orderable_id' => $consultation->id,
                'priority' => $priority,
                'ordered_at' => $orderedAt,
                'status' => 'ordered',
                'ordered_by' => $doctor->id,
            ]);
        }

        // Query the orders using the same sorting logic as the controller
        $sortedOrders = LabOrder::query()
            ->imaging()
            ->excludeExternalReferral()
            ->whereIn('status', ['ordered', 'in_progress'])
            ->orderByRaw("CASE 
                WHEN priority = 'stat' THEN 1 
                WHEN priority = 'urgent' THEN 2 
                ELSE 3 
            END ASC")
            ->orderBy('ordered_at', 'asc')
            ->get();

        // Verify sorting: priority (stat=1, urgent=2, routine=3) then by ordered_at (oldest first)
        $previousPriorityRank = 0;
        $previousOrderedAt = null;

        foreach ($sortedOrders as $order) {
            $currentPriorityRank = match ($order->priority) {
                'stat' => 1,
                'urgent' => 2,
                'routine' => 3,
                default => 4,
            };

            // Priority should be >= previous (lower number = higher priority)
            expect($currentPriorityRank)->toBeGreaterThanOrEqual($previousPriorityRank);

            // If same priority, ordered_at should be >= previous (older first)
            if ($currentPriorityRank === $previousPriorityRank && $previousOrderedAt !== null) {
                expect($order->ordered_at->timestamp)->toBeGreaterThanOrEqual($previousOrderedAt->timestamp);
            }

            $previousPriorityRank = $currentPriorityRank;
            $previousOrderedAt = $order->ordered_at;
        }
    }
});

/**
 * Feature: imaging-radiology-integration, Property 10: Worklist sorting
 * Validates: Requirements 5.1
 *
 * STAT priority orders should always appear before urgent and routine orders.
 */
test('stat priority orders appear first in worklist', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Create shared resources to reuse across iterations
    $imagingService = LabService::factory()->imaging()->create();
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);

    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
        // Clear existing lab orders
        LabOrder::query()->delete();

        // Create patients and consultations for each order
        $patient1 = \App\Models\Patient::factory()->create();
        $checkin1 = \App\Models\PatientCheckin::factory()->create([
            'patient_id' => $patient1->id,
            'department_id' => $department->id,
        ]);
        $consultation1 = Consultation::factory()->create([
            'patient_checkin_id' => $checkin1->id,
            'doctor_id' => $doctor->id,
        ]);

        $patient2 = \App\Models\Patient::factory()->create();
        $checkin2 = \App\Models\PatientCheckin::factory()->create([
            'patient_id' => $patient2->id,
            'department_id' => $department->id,
        ]);
        $consultation2 = Consultation::factory()->create([
            'patient_checkin_id' => $checkin2->id,
            'doctor_id' => $doctor->id,
        ]);

        $patient3 = \App\Models\Patient::factory()->create();
        $checkin3 = \App\Models\PatientCheckin::factory()->create([
            'patient_id' => $patient3->id,
            'department_id' => $department->id,
        ]);
        $consultation3 = Consultation::factory()->create([
            'patient_checkin_id' => $checkin3->id,
            'doctor_id' => $doctor->id,
        ]);

        // Create routine order first (oldest)
        $routineOrder = LabOrder::factory()->create([
            'lab_service_id' => $imagingService->id,
            'consultation_id' => $consultation1->id,
            'orderable_type' => Consultation::class,
            'orderable_id' => $consultation1->id,
            'priority' => 'routine',
            'ordered_at' => now()->subHours(3),
            'status' => 'ordered',
            'ordered_by' => $doctor->id,
        ]);

        // Create urgent order second
        $urgentOrder = LabOrder::factory()->create([
            'lab_service_id' => $imagingService->id,
            'consultation_id' => $consultation2->id,
            'orderable_type' => Consultation::class,
            'orderable_id' => $consultation2->id,
            'priority' => 'urgent',
            'ordered_at' => now()->subHours(2),
            'status' => 'ordered',
            'ordered_by' => $doctor->id,
        ]);

        // Create STAT order last (newest)
        $statOrder = LabOrder::factory()->create([
            'lab_service_id' => $imagingService->id,
            'consultation_id' => $consultation3->id,
            'orderable_type' => Consultation::class,
            'orderable_id' => $consultation3->id,
            'priority' => 'stat',
            'ordered_at' => now()->subHours(1),
            'status' => 'ordered',
            'ordered_by' => $doctor->id,
        ]);

        // Query the orders using the same sorting logic as the controller
        $sortedOrders = LabOrder::query()
            ->imaging()
            ->excludeExternalReferral()
            ->whereIn('status', ['ordered', 'in_progress'])
            ->orderByRaw("CASE 
                WHEN priority = 'stat' THEN 1 
                WHEN priority = 'urgent' THEN 2 
                ELSE 3 
            END ASC")
            ->orderBy('ordered_at', 'asc')
            ->get();

        // STAT should be first, even though it was created last
        expect($sortedOrders[0]->id)->toBe($statOrder->id)
            ->and($sortedOrders[0]->priority)->toBe('stat')
            ->and($sortedOrders[1]->id)->toBe($urgentOrder->id)
            ->and($sortedOrders[1]->priority)->toBe('urgent')
            ->and($sortedOrders[2]->id)->toBe($routineOrder->id)
            ->and($sortedOrders[2]->priority)->toBe('routine');
    }
});

/**
 * Feature: imaging-radiology-integration, Property 11: Worklist filtering
 * Validates: Requirements 5.3
 *
 * For any filter criteria applied to the worklist, all returned orders should
 * match the specified criteria.
 */
test('worklist filtering by priority returns only matching orders', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Create shared resources to reuse across iterations
    $imagingService = LabService::factory()->imaging()->create();
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);

    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
        // Clear existing lab orders
        LabOrder::query()->delete();

        // Create orders with different priorities
        $priorities = ['stat', 'urgent', 'routine'];
        foreach ($priorities as $priority) {
            $orderCount = fake()->numberBetween(1, 5);
            for ($j = 0; $j < $orderCount; $j++) {
                $patient = \App\Models\Patient::factory()->create();
                $checkin = \App\Models\PatientCheckin::factory()->create([
                    'patient_id' => $patient->id,
                    'department_id' => $department->id,
                ]);
                $consultation = Consultation::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'doctor_id' => $doctor->id,
                ]);

                LabOrder::factory()->create([
                    'lab_service_id' => $imagingService->id,
                    'consultation_id' => $consultation->id,
                    'orderable_type' => Consultation::class,
                    'orderable_id' => $consultation->id,
                    'priority' => $priority,
                    'status' => 'ordered',
                    'ordered_by' => $doctor->id,
                ]);
            }
        }

        // Pick a random priority to filter by
        $filterPriority = fake()->randomElement($priorities);

        // Query with filter
        $filteredOrders = LabOrder::query()
            ->imaging()
            ->excludeExternalReferral()
            ->whereIn('status', ['ordered', 'in_progress'])
            ->byPriority($filterPriority)
            ->get();

        // All returned orders should have the filtered priority
        foreach ($filteredOrders as $order) {
            expect($order->priority)->toBe($filterPriority);
        }
    }
});

/**
 * Feature: imaging-radiology-integration, Property 11: Worklist filtering
 * Validates: Requirements 5.3
 *
 * For any filter criteria applied to the worklist, all returned orders should
 * match the specified modality.
 */
test('worklist filtering by modality returns only matching orders', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Create shared resources to reuse across iterations
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);

    // Create imaging services with different modalities
    $modalities = ['X-Ray', 'CT', 'MRI', 'Ultrasound'];
    $imagingServices = [];
    foreach ($modalities as $modality) {
        $imagingServices[$modality] = LabService::factory()->imaging()->create([
            'modality' => $modality,
        ]);
    }

    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
        // Clear existing lab orders
        LabOrder::query()->delete();

        // Create orders with different modalities
        foreach ($modalities as $modality) {
            $orderCount = fake()->numberBetween(1, 3);
            for ($j = 0; $j < $orderCount; $j++) {
                $patient = \App\Models\Patient::factory()->create();
                $checkin = \App\Models\PatientCheckin::factory()->create([
                    'patient_id' => $patient->id,
                    'department_id' => $department->id,
                ]);
                $consultation = Consultation::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'doctor_id' => $doctor->id,
                ]);

                LabOrder::factory()->create([
                    'lab_service_id' => $imagingServices[$modality]->id,
                    'consultation_id' => $consultation->id,
                    'orderable_type' => Consultation::class,
                    'orderable_id' => $consultation->id,
                    'status' => 'ordered',
                    'ordered_by' => $doctor->id,
                ]);
            }
        }

        // Pick a random modality to filter by
        $filterModality = fake()->randomElement($modalities);

        // Query with filter
        $filteredOrders = LabOrder::query()
            ->imaging()
            ->excludeExternalReferral()
            ->whereIn('status', ['ordered', 'in_progress'])
            ->whereHas('labService', function ($q) use ($filterModality) {
                $q->where('modality', $filterModality);
            })
            ->with('labService')
            ->get();

        // All returned orders should have the filtered modality
        foreach ($filteredOrders as $order) {
            expect($order->labService->modality)->toBe($filterModality);
        }
    }
});

/**
 * Feature: imaging-radiology-integration, Property 11: Worklist filtering
 * Validates: Requirements 5.3
 *
 * For any filter criteria applied to the worklist, all returned orders should
 * match the specified status.
 */
test('worklist filtering by status returns only matching orders', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Create shared resources to reuse across iterations
    $imagingService = LabService::factory()->imaging()->create();
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);

    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
        // Clear existing lab orders
        LabOrder::query()->delete();

        // Create orders with different statuses
        $statuses = ['ordered', 'in_progress', 'completed'];
        foreach ($statuses as $status) {
            $orderCount = fake()->numberBetween(1, 3);
            for ($j = 0; $j < $orderCount; $j++) {
                $patient = \App\Models\Patient::factory()->create();
                $checkin = \App\Models\PatientCheckin::factory()->create([
                    'patient_id' => $patient->id,
                    'department_id' => $department->id,
                ]);
                $consultation = Consultation::factory()->create([
                    'patient_checkin_id' => $checkin->id,
                    'doctor_id' => $doctor->id,
                ]);

                LabOrder::factory()->create([
                    'lab_service_id' => $imagingService->id,
                    'consultation_id' => $consultation->id,
                    'orderable_type' => Consultation::class,
                    'orderable_id' => $consultation->id,
                    'status' => $status,
                    'ordered_by' => $doctor->id,
                ]);
            }
        }

        // Pick a random status to filter by
        $filterStatus = fake()->randomElement($statuses);

        // Query with filter
        $filteredOrders = LabOrder::query()
            ->imaging()
            ->excludeExternalReferral()
            ->byStatus($filterStatus)
            ->get();

        // All returned orders should have the filtered status
        foreach ($filteredOrders as $order) {
            expect($order->status)->toBe($filterStatus);
        }
    }
});

/**
 * Feature: imaging-radiology-integration, Property 11: Worklist filtering
 * Validates: Requirements 5.3
 *
 * For any date range filter applied to the worklist, all returned orders should
 * fall within the specified date range.
 */
test('worklist filtering by date range returns only matching orders', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Create shared resources to reuse across iterations
    $imagingService = LabService::factory()->imaging()->create();
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);

    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
        // Clear existing lab orders
        LabOrder::query()->delete();

        // Create orders with different dates
        $dates = [
            now()->subDays(10),
            now()->subDays(5),
            now()->subDays(3),
            now()->subDays(1),
            now(),
        ];

        foreach ($dates as $date) {
            $patient = \App\Models\Patient::factory()->create();
            $checkin = \App\Models\PatientCheckin::factory()->create([
                'patient_id' => $patient->id,
                'department_id' => $department->id,
            ]);
            $consultation = Consultation::factory()->create([
                'patient_checkin_id' => $checkin->id,
                'doctor_id' => $doctor->id,
            ]);

            LabOrder::factory()->create([
                'lab_service_id' => $imagingService->id,
                'consultation_id' => $consultation->id,
                'orderable_type' => Consultation::class,
                'orderable_id' => $consultation->id,
                'ordered_at' => $date,
                'status' => 'ordered',
                'ordered_by' => $doctor->id,
            ]);
        }

        // Define a date range
        $dateFrom = now()->subDays(5)->startOfDay();
        $dateTo = now()->subDays(1)->endOfDay();

        // Query with date filter
        $filteredOrders = LabOrder::query()
            ->imaging()
            ->excludeExternalReferral()
            ->whereIn('status', ['ordered', 'in_progress'])
            ->whereDate('ordered_at', '>=', $dateFrom)
            ->whereDate('ordered_at', '<=', $dateTo)
            ->get();

        // All returned orders should fall within the date range
        foreach ($filteredOrders as $order) {
            expect($order->ordered_at->startOfDay()->timestamp)
                ->toBeGreaterThanOrEqual($dateFrom->timestamp)
                ->and($order->ordered_at->startOfDay()->timestamp)
                ->toBeLessThanOrEqual($dateTo->timestamp);
        }
    }
});

/**
 * Feature: imaging-radiology-integration, Property 13: Completion requires report
 * Validates: Requirements 6.3
 *
 * For any imaging order completion attempt, the system should reject completion
 * if no report text is provided.
 */
test('completion requires report text', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Create shared resources to reuse across iterations
    $imagingService = LabService::factory()->imaging()->create();
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);
    $doctor->givePermissionTo('radiology.report');

    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
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
            'status' => 'in_progress',
            'ordered_by' => $doctor->id,
        ]);

        // Attempt to complete without report
        $response = $this->actingAs($doctor)->patch(route('radiology.orders.complete', $labOrder), [
            'result_notes' => '',
        ]);

        // Should fail validation
        $response->assertSessionHasErrors('result_notes');

        // Order should still be in progress
        $labOrder->refresh();
        expect($labOrder->status)->toBe('in_progress');
    }
});

/**
 * Feature: imaging-radiology-integration, Property 13: Completion requires report
 * Validates: Requirements 6.3
 *
 * For any imaging order completion attempt with a valid report, the system should
 * accept the completion.
 */
test('completion succeeds with valid report text', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Create shared resources to reuse across iterations
    $imagingService = LabService::factory()->imaging()->create();
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);
    $doctor->givePermissionTo('radiology.report');

    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
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
            'status' => 'in_progress',
            'ordered_by' => $doctor->id,
        ]);

        // Generate a random report text (at least 10 characters)
        $reportText = fake()->paragraph(3);

        // Attempt to complete with valid report
        $response = $this->actingAs($doctor)->patch(route('radiology.orders.complete', $labOrder), [
            'result_notes' => $reportText,
        ]);

        // Should redirect to index
        $response->assertRedirect(route('radiology.index'));

        // Order should be completed
        $labOrder->refresh();
        expect($labOrder->status)->toBe('completed')
            ->and($labOrder->result_notes)->toBe($reportText);
    }
});

/**
 * Feature: imaging-radiology-integration, Property 14: Completion metadata
 * Validates: Requirements 6.4
 *
 * For any completed imaging order, the result_entered_at timestamp should be recorded.
 */
test('completion records result_entered_at timestamp', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Create shared resources to reuse across iterations
    $imagingService = LabService::factory()->imaging()->create();
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);
    $doctor->givePermissionTo('radiology.report');

    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
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
            'status' => 'in_progress',
            'ordered_by' => $doctor->id,
            'result_entered_at' => null,
        ]);

        $beforeCompletion = now();

        // Complete the order
        $this->actingAs($doctor)->patch(route('radiology.orders.complete', $labOrder), [
            'result_notes' => fake()->paragraph(3),
        ]);

        $afterCompletion = now();

        // Order should have result_entered_at set
        $labOrder->refresh();
        expect($labOrder->result_entered_at)->not->toBeNull()
            ->and($labOrder->result_entered_at->timestamp)->toBeGreaterThanOrEqual($beforeCompletion->timestamp)
            ->and($labOrder->result_entered_at->timestamp)->toBeLessThanOrEqual($afterCompletion->timestamp);
    }
});

/**
 * Feature: imaging-radiology-integration, Property 7: Image attachment indicator
 * Validates: Requirements 3.5
 *
 * For any imaging order, the "has images" indicator should be true if and only if
 * the order has at least one imaging attachment.
 */
test('has_images indicator is true only when order has attachments', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Create shared resources to reuse across iterations
    $imagingService = LabService::factory()->imaging()->create();
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);

    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
        // Clear existing lab orders and attachments
        \App\Models\ImagingAttachment::query()->delete();
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
            'status' => 'ordered',
            'ordered_by' => $doctor->id,
        ]);

        // Initially, order should have no images
        expect($labOrder->has_images)->toBeFalse();

        // Randomly decide whether to add attachments
        $shouldHaveAttachments = fake()->boolean();
        $attachmentCount = $shouldHaveAttachments ? fake()->numberBetween(1, 5) : 0;

        for ($j = 0; $j < $attachmentCount; $j++) {
            \App\Models\ImagingAttachment::factory()->create([
                'lab_order_id' => $labOrder->id,
                'uploaded_by' => $doctor->id,
            ]);
        }

        // Refresh the model to get updated has_images accessor
        $labOrder->refresh();

        // has_images should be true if and only if there are attachments
        if ($attachmentCount > 0) {
            expect($labOrder->has_images)->toBeTrue();
        } else {
            expect($labOrder->has_images)->toBeFalse();
        }
    }
});

/**
 * Feature: imaging-radiology-integration, Property 7: Image attachment indicator
 * Validates: Requirements 3.5
 *
 * For any imaging order with attachments, the count of attachments should match
 * the number of attachments created.
 */
test('imaging_attachments count matches created attachments', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Create shared resources to reuse across iterations
    $imagingService = LabService::factory()->imaging()->create();
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);

    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
        // Clear existing lab orders and attachments
        \App\Models\ImagingAttachment::query()->delete();
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
            'status' => 'ordered',
            'ordered_by' => $doctor->id,
        ]);

        // Create a random number of attachments
        $attachmentCount = fake()->numberBetween(0, 10);

        for ($j = 0; $j < $attachmentCount; $j++) {
            \App\Models\ImagingAttachment::factory()->create([
                'lab_order_id' => $labOrder->id,
                'uploaded_by' => $doctor->id,
            ]);
        }

        // Refresh the model and load attachments
        $labOrder->refresh();
        $labOrder->load('imagingAttachments');

        // The count should match
        expect($labOrder->imagingAttachments->count())->toBe($attachmentCount);
    }
});

/**
 * Feature: imaging-radiology-integration, Property 6: Imaging order billing
 * Validates: Requirements 3.3
 *
 * For any priced imaging order created through the consultation interface,
 * the system should create a billing charge with the correct amount.
 */
test('priced imaging order creates billing charge with correct amount', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Create shared resources to reuse across iterations
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);
    $doctor->givePermissionTo('lab-orders.create');

    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
        // Clear existing lab orders and charges
        \App\Models\Charge::query()->delete();
        LabOrder::query()->delete();

        // Create a priced imaging service with random price
        $price = fake()->randomFloat(2, 10, 500);
        $imagingService = LabService::factory()->imaging()->active()->create([
            'price' => $price,
        ]);

        $patient = \App\Models\Patient::factory()->create();
        $checkin = \App\Models\PatientCheckin::factory()->create([
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);
        $consultation = Consultation::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'doctor_id' => $doctor->id,
            'status' => 'in_progress',
        ]);

        // Order the imaging study through the controller
        $response = $this->actingAs($doctor)->post(
            route('consultation.lab-orders.store', $consultation),
            [
                'lab_service_id' => $imagingService->id,
                'priority' => fake()->randomElement(['routine', 'urgent', 'stat']),
                'special_instructions' => fake()->optional()->sentence(),
            ]
        );

        $response->assertRedirect()
            ->assertSessionHasNoErrors();

        // Verify a lab order was created (using morphMany relationship)
        $labOrder = LabOrder::where('lab_service_id', $imagingService->id)
            ->where('orderable_type', Consultation::class)
            ->where('orderable_id', $consultation->id)
            ->first();

        expect($labOrder)->not->toBeNull();

        // Verify a charge was created with the correct amount
        $charge = \App\Models\Charge::where('patient_checkin_id', $checkin->id)
            ->where('service_code', $imagingService->code)
            ->first();

        expect($charge)->not->toBeNull()
            ->and((float) $charge->amount)->toBe($price)
            ->and($charge->service_type)->toBe('laboratory')
            ->and($charge->charge_type)->toBe('lab_test');
    }
});

/**
 * Feature: imaging-radiology-integration, Property 6: Imaging order billing
 * Validates: Requirements 3.3
 *
 * For any unpriced imaging order (external referral), no billing charge should be created.
 */
test('unpriced imaging order does not create billing charge', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Create shared resources to reuse across iterations
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);
    $doctor->givePermissionTo('lab-orders.create');

    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
        // Clear existing lab orders and charges
        \App\Models\Charge::query()->delete();
        LabOrder::query()->delete();

        // Create an unpriced imaging service (null price)
        $imagingService = LabService::factory()->imaging()->active()->create([
            'price' => null,
        ]);

        $patient = \App\Models\Patient::factory()->create();
        $checkin = \App\Models\PatientCheckin::factory()->create([
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);
        $consultation = Consultation::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'doctor_id' => $doctor->id,
            'status' => 'in_progress',
        ]);

        $chargeCountBefore = \App\Models\Charge::count();

        // Order the imaging study through the controller
        $response = $this->actingAs($doctor)->post(
            route('consultation.lab-orders.store', $consultation),
            [
                'lab_service_id' => $imagingService->id,
                'priority' => fake()->randomElement(['routine', 'urgent', 'stat']),
                'special_instructions' => fake()->optional()->sentence(),
            ]
        );

        $response->assertRedirect();

        // Verify a lab order was created with external_referral status (using morphMany relationship)
        $labOrder = LabOrder::where('lab_service_id', $imagingService->id)
            ->where('orderable_type', Consultation::class)
            ->where('orderable_id', $consultation->id)
            ->first();

        expect($labOrder)->not->toBeNull()
            ->and($labOrder->status)->toBe('external_referral')
            ->and($labOrder->is_unpriced)->toBeTrue();

        // Verify no charge was created for this order
        $chargeCountAfter = \App\Models\Charge::count();
        expect($chargeCountAfter)->toBe($chargeCountBefore);

        // Double-check: no charge exists for this service code
        $charge = \App\Models\Charge::where('patient_checkin_id', $checkin->id)
            ->where('service_code', $imagingService->code)
            ->first();

        expect($charge)->toBeNull();
    }
});

/**
 * Feature: imaging-radiology-integration, Property 6: Imaging order billing
 * Validates: Requirements 3.3
 *
 * For any imaging order, the billing charge amount should match the service price
 * regardless of priority level.
 */
test('imaging order billing amount is independent of priority', function () {
    // Seed permissions first
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    // Create shared resources to reuse across iterations
    $department = \App\Models\Department::factory()->create();
    $doctor = User::factory()->create();
    $doctor->departments()->attach($department->id);
    $doctor->givePermissionTo('lab-orders.create');

    // Run 100 iterations with random data
    for ($i = 0; $i < 100; $i++) {
        // Clear existing lab orders and charges
        \App\Models\Charge::query()->delete();
        LabOrder::query()->delete();

        // Create a priced imaging service
        $price = fake()->randomFloat(2, 50, 1000);
        $imagingService = LabService::factory()->imaging()->active()->create([
            'price' => $price,
        ]);

        $patient = \App\Models\Patient::factory()->create();
        $checkin = \App\Models\PatientCheckin::factory()->create([
            'patient_id' => $patient->id,
            'department_id' => $department->id,
        ]);
        $consultation = Consultation::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'doctor_id' => $doctor->id,
            'status' => 'in_progress',
        ]);

        // Pick a random priority
        $priority = fake()->randomElement(['routine', 'urgent', 'stat']);

        // Order the imaging study
        $response = $this->actingAs($doctor)->post(
            route('consultation.lab-orders.store', $consultation),
            [
                'lab_service_id' => $imagingService->id,
                'priority' => $priority,
            ]
        );

        $response->assertRedirect();

        // Verify the charge amount matches the service price (not affected by priority)
        $charge = \App\Models\Charge::where('patient_checkin_id', $checkin->id)
            ->where('service_code', $imagingService->code)
            ->first();

        expect($charge)->not->toBeNull()
            ->and((float) $charge->amount)->toBe($price);
    }
});
