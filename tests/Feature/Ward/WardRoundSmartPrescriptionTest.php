<?php

/**
 * Feature: smart-prescription-input
 * Validates: Requirements 10.1, 10.2
 *
 * Tests for Smart mode prescription creation via the Ward Round controller.
 * This ensures the same Smart mode functionality available in Consultation
 * is also available in Ward Rounds.
 */

use App\Models\Department;
use App\Models\Drug;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\PatientCheckin;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardRound;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $adminRole = Role::create(['name' => 'Admin']);

    // Create required permissions for ward rounds
    Permission::create(['name' => 'ward_rounds.view']);
    Permission::create(['name' => 'ward_rounds.create']);
    Permission::create(['name' => 'ward_rounds.update']);
    Permission::create(['name' => 'ward_rounds.delete']);

    // Assign permissions to admin role
    $adminRole->givePermissionTo([
        'ward_rounds.view',
        'ward_rounds.create',
        'ward_rounds.update',
        'ward_rounds.delete',
    ]);

    $this->user->assignRole($adminRole);

    $this->department = Department::factory()->create();
    $this->department->users()->attach($this->user->id);

    $this->ward = Ward::factory()->create();
    $this->patient = Patient::factory()->create();
    $this->patientCheckin = PatientCheckin::factory()->create([
        'patient_id' => $this->patient->id,
        'department_id' => $this->department->id,
        'status' => 'admitted',
    ]);
    $this->admission = PatientAdmission::factory()->create([
        'patient_id' => $this->patient->id,
        'ward_id' => $this->ward->id,
        'status' => 'admitted',
    ]);
    // Create ward round with the test user as the doctor
    $this->wardRound = WardRound::factory()->create([
        'patient_admission_id' => $this->admission->id,
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
 * Smart mode prescription stores same data fields as Classic mode in Ward Round.
 */
it('creates ward round prescription with smart input', function () {
    $response = $this->actingAs($this->user)
        ->post("/admissions/{$this->admission->id}/ward-rounds/{$this->wardRound->id}/prescriptions", [
            'medication_name' => $this->drug->name,
            'drug_id' => $this->drug->id,
            'use_smart_mode' => true,
            'smart_input' => '2 BD x 5 days',
            'instructions' => 'Take after meals',
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('prescriptions', [
        'prescribable_type' => WardRound::class,
        'prescribable_id' => $this->wardRound->id,
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
 * Schedule pattern is stored for split dose prescriptions in Ward Round.
 */
it('stores schedule_pattern for ward round split dose prescriptions', function () {
    $response = $this->actingAs($this->user)
        ->post("/admissions/{$this->admission->id}/ward-rounds/{$this->wardRound->id}/prescriptions", [
            'medication_name' => $this->drug->name,
            'drug_id' => $this->drug->id,
            'use_smart_mode' => true,
            'smart_input' => '1-0-1 x 30 days',
        ]);

    $response->assertRedirect();

    $prescription = Prescription::where('prescribable_type', WardRound::class)
        ->where('prescribable_id', $this->wardRound->id)
        ->first();
    expect($prescription)->not->toBeNull();
    expect($prescription->schedule_pattern)->not->toBeNull();
    expect($prescription->schedule_pattern['type'])->toBe('split_dose');
    expect($prescription->schedule_pattern['pattern']['morning'])->toBe(1);
    expect($prescription->schedule_pattern['pattern']['noon'])->toBe(0);
    expect($prescription->schedule_pattern['pattern']['evening'])->toBe(1);
    expect($prescription->quantity_to_dispense)->toBe(60);
});

/**
 * Validates: Requirements 10.2
 * Schedule pattern is stored for taper prescriptions in Ward Round.
 */
it('stores schedule_pattern for ward round taper prescriptions', function () {
    $response = $this->actingAs($this->user)
        ->post("/admissions/{$this->admission->id}/ward-rounds/{$this->wardRound->id}/prescriptions", [
            'medication_name' => $this->drug->name,
            'drug_id' => $this->drug->id,
            'use_smart_mode' => true,
            'smart_input' => '4-3-2-1 taper',
        ]);

    $response->assertRedirect();

    $prescription = Prescription::where('prescribable_type', WardRound::class)
        ->where('prescribable_id', $this->wardRound->id)
        ->first();
    expect($prescription)->not->toBeNull();
    expect($prescription->schedule_pattern)->not->toBeNull();
    expect($prescription->schedule_pattern['type'])->toBe('taper');
    expect($prescription->schedule_pattern['doses'])->toBe([4, 3, 2, 1]);
    expect($prescription->quantity_to_dispense)->toBe(10);
});

/**
 * Validates: Requirements 10.1
 * Classic mode still works unchanged in Ward Round.
 */
it('creates ward round prescription with classic mode', function () {
    $response = $this->actingAs($this->user)
        ->post("/admissions/{$this->admission->id}/ward-rounds/{$this->wardRound->id}/prescriptions", [
            'medication_name' => $this->drug->name,
            'drug_id' => $this->drug->id,
            'use_smart_mode' => false,
            'dose_quantity' => '2',
            'frequency' => 'Twice daily (BD)',
            'duration' => '5 days',
            'quantity_to_dispense' => 20,
            'instructions' => 'Take after meals',
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('prescriptions', [
        'prescribable_type' => WardRound::class,
        'prescribable_id' => $this->wardRound->id,
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
 * Smart mode returns validation error for invalid input in Ward Round.
 */
it('returns validation error for invalid smart input in ward round', function () {
    $response = $this->actingAs($this->user)
        ->post("/admissions/{$this->admission->id}/ward-rounds/{$this->wardRound->id}/prescriptions", [
            'medication_name' => $this->drug->name,
            'drug_id' => $this->drug->id,
            'use_smart_mode' => true,
            'smart_input' => 'invalid prescription text',
        ]);

    $response->assertSessionHasErrors(['smart_input']);
});

/**
 * Validates: Requirements 10.2
 * STAT prescription stores correct data in Ward Round.
 */
it('creates STAT prescription with smart mode in ward round', function () {
    $response = $this->actingAs($this->user)
        ->post("/admissions/{$this->admission->id}/ward-rounds/{$this->wardRound->id}/prescriptions", [
            'medication_name' => $this->drug->name,
            'drug_id' => $this->drug->id,
            'use_smart_mode' => true,
            'smart_input' => '2 STAT',
        ]);

    $response->assertRedirect();

    $prescription = Prescription::where('prescribable_type', WardRound::class)
        ->where('prescribable_id', $this->wardRound->id)
        ->first();
    expect($prescription)->not->toBeNull();
    expect($prescription->dose_quantity)->toBe('2');
    expect($prescription->frequency)->toBe('Immediately (STAT)');
    expect($prescription->duration)->toBe('Single dose');
    expect($prescription->quantity_to_dispense)->toBe(2);
    expect($prescription->schedule_pattern)->toBeNull();
});

/**
 * Validates: Requirements 10.2
 * PRN prescription stores correct data in Ward Round.
 */
it('creates PRN prescription with smart mode in ward round', function () {
    $response = $this->actingAs($this->user)
        ->post("/admissions/{$this->admission->id}/ward-rounds/{$this->wardRound->id}/prescriptions", [
            'medication_name' => $this->drug->name,
            'drug_id' => $this->drug->id,
            'use_smart_mode' => true,
            'smart_input' => '2 PRN',
        ]);

    $response->assertRedirect();

    $prescription = Prescription::where('prescribable_type', WardRound::class)
        ->where('prescribable_id', $this->wardRound->id)
        ->first();
    expect($prescription)->not->toBeNull();
    expect($prescription->dose_quantity)->toBe('2');
    expect($prescription->frequency)->toBe('As needed (PRN)');
    expect($prescription->duration)->toBe('As needed');
    expect($prescription->schedule_pattern)->toBeNull();
});
