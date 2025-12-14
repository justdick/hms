<?php

/**
 * Feature: smart-prescription-input, Property 1: Mode switching preserves drug selection
 * Validates: Requirements 1.4
 *
 * Note: This property is primarily a frontend behavior test. The React component
 * PrescriptionFormSection maintains mode state and preserves drug_id when switching
 * between 'smart' and 'classic' modes while clearing other form fields.
 *
 * This test file validates the backend API behavior that supports mode switching,
 * ensuring the parse endpoint works correctly regardless of mode context.
 */

use App\Models\Consultation;
use App\Models\Department;
use App\Models\Drug;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $adminRole = Role::create(['name' => 'Admin']);
    $this->user->assignRole($adminRole);

    $this->department = Department::factory()->create();
    $this->department->users()->attach($this->user->id);
});

/**
 * Feature: smart-prescription-input, Property 1: Mode switching preserves drug selection
 * Validates: Requirements 1.4
 *
 * Backend validation: The parse endpoint should work with any drug_id,
 * supporting the frontend's ability to preserve drug selection across mode switches.
 */
it('parse endpoint accepts drug_id parameter consistently', function () {
    $drug = Drug::factory()->create([
        'name' => 'Amoxicillin',
        'form' => 'capsule',
        'unit_type' => 'piece',
    ]);

    // First parse with drug_id
    $response1 = $this->actingAs($this->user)
        ->postJson('/consultation/prescriptions/parse', [
            'input' => '2 BD x 5 days',
            'drug_id' => $drug->id,
        ]);

    $response1->assertSuccessful();
    expect($response1->json('isValid'))->toBeTrue();

    // Second parse with same drug_id (simulating mode switch back)
    $response2 = $this->actingAs($this->user)
        ->postJson('/consultation/prescriptions/parse', [
            'input' => '1 TDS x 7 days',
            'drug_id' => $drug->id,
        ]);

    $response2->assertSuccessful();
    expect($response2->json('isValid'))->toBeTrue();
});

/**
 * Feature: smart-prescription-input, Property 1: Mode switching preserves drug selection
 * Validates: Requirements 1.4
 *
 * The parse endpoint should work without drug_id as well,
 * supporting initial state before drug selection.
 */
it('parse endpoint works without drug_id', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/consultation/prescriptions/parse', [
            'input' => '2 BD x 5 days',
        ]);

    $response->assertSuccessful();
    expect($response->json('isValid'))->toBeTrue();
});

/**
 * Feature: smart-prescription-input, Property 8: Smart mode produces same data structure as Classic mode
 * Validates: Requirements 10.1
 *
 * For any valid smart input that can be expressed in Classic mode,
 * the resulting prescription data (dose_quantity, frequency, duration, quantity_to_dispense)
 * should be identical to what Classic mode would produce for the same prescription.
 */
it('smart mode produces same data structure as classic mode', function () {
    // Test cases that can be expressed in both modes
    // Note: Parser returns frequency with the code used in input (BD vs BID)
    $testCases = [
        [
            'smart_input' => '2 BD x 5 days',
            'classic' => [
                'dose_quantity' => '2',
                'frequency' => 'Twice daily (BD)',
                'duration' => '5 days',
                'quantity_to_dispense' => 20,
            ],
        ],
        [
            'smart_input' => '1 TDS x 7 days',
            'classic' => [
                'dose_quantity' => '1',
                'frequency' => 'Three times daily (TDS)',
                'duration' => '7 days',
                'quantity_to_dispense' => 21,
            ],
        ],
        [
            'smart_input' => '1 OD x 30 days',
            'classic' => [
                'dose_quantity' => '1',
                'frequency' => 'Once daily (OD)',
                'duration' => '30 days',
                'quantity_to_dispense' => 30,
            ],
        ],
        [
            'smart_input' => '2 QDS x 7 days',
            'classic' => [
                'dose_quantity' => '2',
                'frequency' => 'Four times daily (QDS)',
                'duration' => '7 days',
                'quantity_to_dispense' => 56,
            ],
        ],
    ];

    foreach ($testCases as $testCase) {
        $response = $this->actingAs($this->user)
            ->postJson('/consultation/prescriptions/parse', [
                'input' => $testCase['smart_input'],
            ]);

        $response->assertOk();
        $data = $response->json();

        expect($data['isValid'])->toBeTrue()
            ->and($data['doseQuantity'])->toBe($testCase['classic']['dose_quantity'])
            ->and($data['frequency'])->toBe($testCase['classic']['frequency'])
            ->and($data['durationDays'])->toBe((int) filter_var($testCase['classic']['duration'], FILTER_SANITIZE_NUMBER_INT))
            ->and($data['quantityToDispense'])->toBe($testCase['classic']['quantity_to_dispense']);
    }
});

/**
 * Feature: smart-prescription-input, Property 8: Smart mode produces same data structure as Classic mode
 * Validates: Requirements 10.1
 *
 * Property test: For any combination of dose (1-4), frequency (OD, BD, TDS, QDS),
 * and duration (3, 5, 7, 14, 30 days), smart mode should produce the same
 * quantity calculation as classic mode would.
 */
it('smart mode quantity calculation matches classic mode formula', function () {
    $doses = [1, 2, 3, 4];
    $frequencies = [
        'OD' => ['times_per_day' => 1],
        'BD' => ['times_per_day' => 2],
        'TDS' => ['times_per_day' => 3],
        'QDS' => ['times_per_day' => 4],
    ];
    $durations = [3, 5, 7, 14, 30];

    // Test 20 random combinations
    for ($i = 0; $i < 20; $i++) {
        $dose = $doses[array_rand($doses)];
        $freqCode = array_rand($frequencies);
        $freqData = $frequencies[$freqCode];
        $days = $durations[array_rand($durations)];

        $smartInput = "{$dose} {$freqCode} x {$days} days";

        // Classic mode formula: dose × times_per_day × days
        $expectedQuantity = $dose * $freqData['times_per_day'] * $days;

        $response = $this->actingAs($this->user)
            ->postJson('/consultation/prescriptions/parse', [
                'input' => $smartInput,
            ]);

        $response->assertOk();
        $data = $response->json();

        expect($data['isValid'])->toBeTrue()
            ->and($data['quantityToDispense'])->toBe($expectedQuantity);
    }
});

/**
 * Feature: smart-prescription-input
 * Validates: Requirements 10.1, 10.2, 10.3
 *
 * Tests for Smart mode prescription creation via the consultation controller.
 */
describe('Smart Mode Prescription Creation', function () {
    beforeEach(function () {
        $this->patient = Patient::factory()->create();
        $this->patientCheckin = PatientCheckin::factory()->create([
            'patient_id' => $this->patient->id,
            'department_id' => $this->department->id,
            'status' => 'in_consultation',
        ]);
        $this->consultation = Consultation::factory()->create([
            'patient_checkin_id' => $this->patientCheckin->id,
            'doctor_id' => $this->user->id,
            'status' => 'in_progress',
        ]);
        $this->drug = Drug::factory()->create([
            'name' => 'Amoxicillin 500mg',
            'form' => 'capsule',
            'unit_type' => 'piece',
            'unit_price' => 2.50,
        ]);
    });

    /**
     * Validates: Requirements 10.1
     * Smart mode prescription stores same data fields as Classic mode.
     */
    it('creates prescription with smart input', function () {
        $response = $this->actingAs($this->user)
            ->post("/consultation/{$this->consultation->id}/prescriptions", [
                'medication_name' => $this->drug->name,
                'drug_id' => $this->drug->id,
                'use_smart_mode' => true,
                'smart_input' => '2 BD x 5 days',
                'instructions' => 'Take after meals',
            ]);

        $response->assertRedirect()
            ->assertSessionHas('success', 'Prescription added successfully.');

        $this->assertDatabaseHas('prescriptions', [
            'consultation_id' => $this->consultation->id,
            'medication_name' => $this->drug->name,
            'drug_id' => $this->drug->id,
            'dose_quantity' => '2',
            'frequency' => 'Twice daily (BD)',
            'duration' => '5 days',
            'quantity_to_dispense' => 20,
            'instructions' => 'Take after meals',
            'status' => 'prescribed',
        ]);
    });

    /**
     * Validates: Requirements 10.2
     * Schedule pattern is stored for custom schedules.
     */
    it('stores schedule_pattern for split dose prescriptions', function () {
        $response = $this->actingAs($this->user)
            ->post("/consultation/{$this->consultation->id}/prescriptions", [
                'medication_name' => $this->drug->name,
                'drug_id' => $this->drug->id,
                'use_smart_mode' => true,
                'smart_input' => '1-0-1 x 30 days',
            ]);

        $response->assertRedirect();

        $prescription = Prescription::where('consultation_id', $this->consultation->id)->first();
        expect($prescription)->not->toBeNull();
        expect($prescription->schedule_pattern)->not->toBeNull();
        expect($prescription->schedule_pattern['type'])->toBe('split_dose');
        // JSON decoding converts floats to ints when they're whole numbers
        expect($prescription->schedule_pattern['pattern']['morning'])->toBe(1);
        expect($prescription->schedule_pattern['pattern']['noon'])->toBe(0);
        expect($prescription->schedule_pattern['pattern']['evening'])->toBe(1);
        expect($prescription->quantity_to_dispense)->toBe(60);
    });

    /**
     * Validates: Requirements 10.2
     * Schedule pattern is stored for taper prescriptions.
     */
    it('stores schedule_pattern for taper prescriptions', function () {
        $response = $this->actingAs($this->user)
            ->post("/consultation/{$this->consultation->id}/prescriptions", [
                'medication_name' => $this->drug->name,
                'drug_id' => $this->drug->id,
                'use_smart_mode' => true,
                'smart_input' => '4-3-2-1 taper',
            ]);

        $response->assertRedirect();

        $prescription = Prescription::where('consultation_id', $this->consultation->id)->first();
        expect($prescription)->not->toBeNull();
        expect($prescription->schedule_pattern)->not->toBeNull();
        expect($prescription->schedule_pattern['type'])->toBe('taper');
        // JSON decoding converts floats to ints when they're whole numbers
        expect($prescription->schedule_pattern['doses'])->toBe([4, 3, 2, 1]);
        expect($prescription->quantity_to_dispense)->toBe(10);
    });

    /**
     * Validates: Requirements 10.1
     * Classic mode still works unchanged.
     */
    it('creates prescription with classic mode', function () {
        $response = $this->actingAs($this->user)
            ->post("/consultation/{$this->consultation->id}/prescriptions", [
                'medication_name' => $this->drug->name,
                'drug_id' => $this->drug->id,
                'use_smart_mode' => false,
                'dose_quantity' => '2',
                'frequency' => 'Twice daily (BD)',
                'duration' => '5 days',
                'quantity_to_dispense' => 20,
                'instructions' => 'Take after meals',
            ]);

        $response->assertRedirect()
            ->assertSessionHas('success', 'Prescription added successfully.');

        $this->assertDatabaseHas('prescriptions', [
            'consultation_id' => $this->consultation->id,
            'medication_name' => $this->drug->name,
            'drug_id' => $this->drug->id,
            'dose_quantity' => '2',
            'frequency' => 'Twice daily (BD)',
            'duration' => '5 days',
            'quantity_to_dispense' => 20,
            'instructions' => 'Take after meals',
            'status' => 'prescribed',
        ]);
    });

    /**
     * Validates: Requirements 10.1
     * Smart mode returns validation error for invalid input.
     */
    it('returns validation error for invalid smart input', function () {
        $response = $this->actingAs($this->user)
            ->post("/consultation/{$this->consultation->id}/prescriptions", [
                'medication_name' => $this->drug->name,
                'drug_id' => $this->drug->id,
                'use_smart_mode' => true,
                'smart_input' => 'invalid prescription text',
            ]);

        $response->assertSessionHasErrors(['smart_input']);
    });

    /**
     * Validates: Requirements 10.1
     * Smart mode requires smart_input when use_smart_mode is true.
     */
    it('requires smart_input when use_smart_mode is true', function () {
        $response = $this->actingAs($this->user)
            ->post("/consultation/{$this->consultation->id}/prescriptions", [
                'medication_name' => $this->drug->name,
                'drug_id' => $this->drug->id,
                'use_smart_mode' => true,
                'smart_input' => '',
            ]);

        $response->assertSessionHasErrors(['smart_input']);
    });

    /**
     * Validates: Requirements 10.1
     * Classic mode requires frequency and duration.
     */
    it('requires frequency and duration in classic mode', function () {
        $response = $this->actingAs($this->user)
            ->post("/consultation/{$this->consultation->id}/prescriptions", [
                'medication_name' => $this->drug->name,
                'drug_id' => $this->drug->id,
                'use_smart_mode' => false,
                'dose_quantity' => '2',
            ]);

        $response->assertSessionHasErrors(['frequency', 'duration']);
    });

    /**
     * Validates: Requirements 10.2
     * STAT prescription stores correct data.
     */
    it('creates STAT prescription with smart mode', function () {
        $response = $this->actingAs($this->user)
            ->post("/consultation/{$this->consultation->id}/prescriptions", [
                'medication_name' => $this->drug->name,
                'drug_id' => $this->drug->id,
                'use_smart_mode' => true,
                'smart_input' => '2 STAT',
            ]);

        $response->assertRedirect();

        $prescription = Prescription::where('consultation_id', $this->consultation->id)->first();
        expect($prescription)->not->toBeNull();
        expect($prescription->dose_quantity)->toBe('2');
        expect($prescription->frequency)->toBe('Immediately (STAT)');
        expect($prescription->duration)->toBe('Single dose');
        expect($prescription->quantity_to_dispense)->toBe(2);
        // STAT doesn't have a schedule_pattern (it's a single immediate dose)
        expect($prescription->schedule_pattern)->toBeNull();
    });

    /**
     * Validates: Requirements 10.2
     * PRN prescription stores correct data.
     */
    it('creates PRN prescription with smart mode', function () {
        $response = $this->actingAs($this->user)
            ->post("/consultation/{$this->consultation->id}/prescriptions", [
                'medication_name' => $this->drug->name,
                'drug_id' => $this->drug->id,
                'use_smart_mode' => true,
                'smart_input' => '2 PRN',
            ]);

        $response->assertRedirect();

        $prescription = Prescription::where('consultation_id', $this->consultation->id)->first();
        expect($prescription)->not->toBeNull();
        expect($prescription->dose_quantity)->toBe('2');
        expect($prescription->frequency)->toBe('As needed (PRN)');
        expect($prescription->duration)->toBe('As needed');
        // PRN doesn't have a schedule_pattern (it's as-needed)
        expect($prescription->schedule_pattern)->toBeNull();
    });
});
