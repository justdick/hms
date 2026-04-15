<?php

use App\Models\Consultation;
use App\Models\Department;
use App\Models\Drug;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\PatientCheckin;
use App\Models\Prescription;
use App\Models\User;
use App\Models\WardRound;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->department = Department::factory()->create();

    $doctorRole = Role::firstOrCreate(['name' => 'doctor']);
    $permissions = [
        'ward_rounds.view',
        'ward_rounds.create',
        'ward_rounds.update',
    ];
    foreach ($permissions as $permName) {
        $perm = Permission::firstOrCreate(['name' => $permName]);
        $doctorRole->givePermissionTo($perm);
    }

    $this->doctor = User::factory()->create();
    $this->doctor->assignRole('doctor');
    $this->doctor->departments()->attach($this->department->id);
    $this->patient = Patient::factory()->create();

    // Create admission with consultation
    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $this->patient->id,
        'department_id' => $this->department->id,
    ]);
    $this->consultation = Consultation::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'doctor_id' => $this->doctor->id,
        'status' => 'completed',
    ]);
    $this->admission = PatientAdmission::factory()->create([
        'patient_id' => $this->patient->id,
        'consultation_id' => $this->consultation->id,
    ]);
});

// Helper to create prescription without triggering events
function createWardRoundPrescription(array $attributes): Prescription
{
    return Prescription::withoutEvents(function () use ($attributes) {
        return Prescription::factory()->create($attributes);
    });
}

it('can refill prescriptions from a previous ward round', function () {
    // Create a previous completed ward round with a prescription
    $previousRound = WardRound::factory()->completed()->create([
        'patient_admission_id' => $this->admission->id,
        'doctor_id' => $this->doctor->id,
    ]);

    $drug = Drug::factory()->create(['is_active' => true]);
    $previousPrescription = createWardRoundPrescription([
        'consultation_id' => null,
        'prescribable_type' => WardRound::class,
        'prescribable_id' => $previousRound->id,
        'drug_id' => $drug->id,
        'medication_name' => $drug->name,
        'dose_quantity' => '500mg',
        'frequency' => 'Twice daily',
        'duration' => '5 days',
        'quantity_to_dispense' => 10,
        'instructions' => 'After meals',
    ]);

    // Create current in-progress ward round
    $currentRound = WardRound::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'doctor_id' => $this->doctor->id,
    ]);

    $response = $this->actingAs($this->doctor)
        ->post("/admissions/{$this->admission->id}/ward-rounds/{$currentRound->id}/prescriptions/refill", [
            'prescription_ids' => [$previousPrescription->id],
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $newPrescription = Prescription::where('prescribable_type', WardRound::class)
        ->where('prescribable_id', $currentRound->id)
        ->first();

    expect($newPrescription)->not->toBeNull();
    expect($newPrescription->refilled_from_prescription_id)->toBe($previousPrescription->id);
    expect($newPrescription->drug_id)->toBe($drug->id);
    expect($newPrescription->medication_name)->toBe($drug->name);
    expect($newPrescription->frequency)->toBe('Twice daily');
    expect($newPrescription->duration)->toBe('5 days');
    expect($newPrescription->dose_quantity)->toBe('500mg');
    expect($newPrescription->instructions)->toBe('After meals');
    expect($newPrescription->status)->toBe('prescribed');
});

it('can refill prescriptions from the initial consultation', function () {
    $drug = Drug::factory()->create(['is_active' => true]);
    $consultationPrescription = createWardRoundPrescription([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $drug->id,
        'medication_name' => $drug->name,
        'dose_quantity' => '1',
        'frequency' => 'Once daily',
        'duration' => '7 days',
        'quantity_to_dispense' => 7,
    ]);

    $currentRound = WardRound::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'doctor_id' => $this->doctor->id,
    ]);

    $response = $this->actingAs($this->doctor)
        ->post("/admissions/{$this->admission->id}/ward-rounds/{$currentRound->id}/prescriptions/refill", [
            'prescription_ids' => [$consultationPrescription->id],
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $newPrescription = Prescription::where('prescribable_type', WardRound::class)
        ->where('prescribable_id', $currentRound->id)
        ->first();

    expect($newPrescription)->not->toBeNull();
    expect($newPrescription->refilled_from_prescription_id)->toBe($consultationPrescription->id);
    expect($newPrescription->drug_id)->toBe($drug->id);
});

it('can bulk refill multiple prescriptions from different sources', function () {
    // Prescription from initial consultation
    $drug1 = Drug::factory()->create(['is_active' => true]);
    $consultationRx = createWardRoundPrescription([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $drug1->id,
        'medication_name' => $drug1->name,
    ]);

    // Prescription from previous ward round
    $previousRound = WardRound::factory()->completed()->create([
        'patient_admission_id' => $this->admission->id,
        'doctor_id' => $this->doctor->id,
    ]);
    $drug2 = Drug::factory()->create(['is_active' => true]);
    $wardRoundRx = createWardRoundPrescription([
        'consultation_id' => null,
        'prescribable_type' => WardRound::class,
        'prescribable_id' => $previousRound->id,
        'drug_id' => $drug2->id,
        'medication_name' => $drug2->name,
    ]);

    $currentRound = WardRound::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'doctor_id' => $this->doctor->id,
    ]);

    $response = $this->actingAs($this->doctor)
        ->post("/admissions/{$this->admission->id}/ward-rounds/{$currentRound->id}/prescriptions/refill", [
            'prescription_ids' => [$consultationRx->id, $wardRoundRx->id],
        ]);

    $response->assertRedirect();

    $newPrescriptions = Prescription::where('prescribable_type', WardRound::class)
        ->where('prescribable_id', $currentRound->id)
        ->get();

    expect($newPrescriptions)->toHaveCount(2);
});

it('skips inactive drugs during ward round refill', function () {
    $previousRound = WardRound::factory()->completed()->create([
        'patient_admission_id' => $this->admission->id,
        'doctor_id' => $this->doctor->id,
    ]);

    $inactiveDrug = Drug::factory()->create(['is_active' => false]);
    $prescription = createWardRoundPrescription([
        'consultation_id' => null,
        'prescribable_type' => WardRound::class,
        'prescribable_id' => $previousRound->id,
        'drug_id' => $inactiveDrug->id,
        'medication_name' => $inactiveDrug->name,
    ]);

    $currentRound = WardRound::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'doctor_id' => $this->doctor->id,
    ]);

    $response = $this->actingAs($this->doctor)
        ->post("/admissions/{$this->admission->id}/ward-rounds/{$currentRound->id}/prescriptions/refill", [
            'prescription_ids' => [$prescription->id],
        ]);

    $response->assertRedirect();

    $newPrescriptions = Prescription::where('prescribable_type', WardRound::class)
        ->where('prescribable_id', $currentRound->id)
        ->get();

    expect($newPrescriptions)->toHaveCount(0);
});

it('can refill on a completed ward round within 24 hours', function () {
    $previousRound = WardRound::factory()->completed()->create([
        'patient_admission_id' => $this->admission->id,
        'doctor_id' => $this->doctor->id,
    ]);

    $drug = Drug::factory()->create(['is_active' => true]);
    $prescription = createWardRoundPrescription([
        'consultation_id' => null,
        'prescribable_type' => WardRound::class,
        'prescribable_id' => $previousRound->id,
        'drug_id' => $drug->id,
        'medication_name' => $drug->name,
    ]);

    $completedRound = WardRound::factory()->completed()->create([
        'patient_admission_id' => $this->admission->id,
        'doctor_id' => $this->doctor->id,
    ]);

    $response = $this->actingAs($this->doctor)
        ->post("/admissions/{$this->admission->id}/ward-rounds/{$completedRound->id}/prescriptions/refill", [
            'prescription_ids' => [$prescription->id],
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
});
