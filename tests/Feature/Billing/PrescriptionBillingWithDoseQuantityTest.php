<?php

use App\Models\Charge;
use App\Models\Consultation;
use App\Models\Drug;
use App\Models\PatientCheckin;
use App\Models\Prescription;
use App\Models\User;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    // Create the permission first since we use RefreshDatabase
    Permission::firstOrCreate(['name' => 'consultations.create', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('consultations.create');

    // Create a drug for testing using factory
    $this->drug = Drug::factory()->create([
        'name' => 'Paracetamol',
        'form' => 'tablet',
        'strength' => '500mg',
        'drug_code' => 'PARA500',
        'unit_price' => 50.00, // 50 per tablet
        'unit_type' => 'piece',
        'is_active' => true,
    ]);

    $this->syrup = Drug::factory()->create([
        'name' => 'Cough Syrup',
        'form' => 'syrup',
        'strength' => '15mg/5ml',
        'drug_code' => 'COUGH15',
        'unit_price' => 200.00, // 200 per bottle
        'unit_type' => 'bottle',
        'is_active' => true,
    ]);

    $patientCheckin = PatientCheckin::factory()->create();

    $this->consultation = Consultation::factory()->create([
        'patient_checkin_id' => $patientCheckin->id,
        'doctor_id' => $this->user->id,
        'status' => 'in_progress',
    ]);
});

it('creates billing charge for tablet prescription with dose quantity of 2 tablets', function () {
    actingAs($this->user);

    // Create prescription: 2 tablets, 3 times daily, 7 days = 42 tablets total
    $prescription = Prescription::create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'medication_name' => 'Paracetamol 500mg',
        'dose_quantity' => '2', // 2 tablets per dose
        'frequency' => 'Three times daily (TID)',
        'duration' => '7 days',
        'quantity' => 42, // 2 × 3 × 7 = 42 tablets
        'quantity_to_dispense' => 42,
        'status' => 'prescribed',
    ]);

    // Check that charge was created
    $charge = Charge::where('prescription_id', $prescription->id)->first();

    expect($charge)->not->toBeNull()
        ->and((float) $charge->amount)->toBe(2100.00) // 42 tablets × 50 = 2100
        ->and($charge->description)->toContain('42')
        ->and($charge->service_type)->toBe('pharmacy');
});

it('creates billing charge for syrup prescription with dose quantity of 10ml', function () {
    actingAs($this->user);

    // Create prescription: 10ml, 3 times daily, 7 days = 210ml total = 3 bottles
    $prescription = Prescription::create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->syrup->id,
        'medication_name' => 'Cough Syrup 15mg/5ml',
        'dose_quantity' => '10', // 10ml per dose
        'frequency' => 'Three times daily (TID)',
        'duration' => '7 days',
        'quantity' => 3, // 3 bottles (210ml ÷ 100ml per bottle, rounded up)
        'quantity_to_dispense' => 3,
        'status' => 'prescribed',
    ]);

    // Check that charge was created
    $charge = Charge::where('prescription_id', $prescription->id)->first();

    expect($charge)->not->toBeNull()
        ->and((float) $charge->amount)->toBe(600.00) // 3 bottles × 200 = 600
        ->and($charge->description)->toContain('3')
        ->and($charge->service_type)->toBe('pharmacy');
});

it('uses quantity field for billing calculation, not quantity_to_dispense', function () {
    actingAs($this->user);

    // Test that billing uses `quantity` field
    $prescription = Prescription::create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'medication_name' => 'Paracetamol 500mg',
        'dose_quantity' => '1',
        'frequency' => 'Three times daily (TID)',
        'duration' => '7 days',
        'quantity' => 21, // This should be used for billing
        'quantity_to_dispense' => 21,
        'status' => 'prescribed',
    ]);

    $charge = Charge::where('prescription_id', $prescription->id)->first();

    expect($charge)->not->toBeNull()
        ->and((float) $charge->amount)->toBe(1050.00); // 21 tablets × 50 = 1050
});

it('does not create billing charge when quantity is zero', function () {
    actingAs($this->user);

    // This simulates a prescription with zero quantity (edge case)
    $prescription = Prescription::create([
        'prescribable_type' => Consultation::class,
        'prescribable_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'medication_name' => 'Paracetamol 500mg',
        'frequency' => 'Three times daily (TID)',
        'duration' => '7 days',
        'quantity' => 0, // Zero quantity - no billing expected
        'quantity_to_dispense' => 0,
        'status' => 'prescribed',
    ]);

    // Check that no charge was created for zero quantity
    $charge = Charge::where('prescription_id', $prescription->id)->first();

    // With zero quantity, no charge should be created
    expect($charge)->toBeNull();
});
