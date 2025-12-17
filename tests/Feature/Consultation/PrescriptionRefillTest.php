<?php

use App\Models\Consultation;
use App\Models\Department;
use App\Models\Drug;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\Prescription;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Create department
    $this->department = Department::factory()->create();

    // Create doctor role with necessary permissions
    $doctorRole = Role::firstOrCreate(['name' => 'doctor']);
    $permissions = [
        'consultations.view-all',
        'consultations.create',
        'consultations.update-own',
        'consultations.complete',
    ];
    foreach ($permissions as $permName) {
        $perm = Permission::firstOrCreate(['name' => $permName]);
        $doctorRole->givePermissionTo($perm);
    }

    $this->doctor = User::factory()->create();
    $this->doctor->assignRole('doctor');
    $this->doctor->departments()->attach($this->department->id);
    $this->patient = Patient::factory()->create();
});

// Helper to create prescription without triggering events
function createPrescriptionWithoutEvents(array $attributes): Prescription
{
    return Prescription::withoutEvents(function () use ($attributes) {
        return Prescription::factory()->create($attributes);
    });
}

it('can refill prescriptions from previous consultations', function () {
    // Create a previous consultation with prescriptions
    $previousCheckin = PatientCheckin::factory()->create([
        'patient_id' => $this->patient->id,
        'department_id' => $this->department->id,
    ]);
    $previousConsultation = Consultation::factory()->create([
        'patient_checkin_id' => $previousCheckin->id,
        'doctor_id' => $this->doctor->id,
        'status' => 'completed',
    ]);

    $drug = Drug::factory()->create(['is_active' => true]);
    $previousPrescription = createPrescriptionWithoutEvents([
        'consultation_id' => $previousConsultation->id,
        'drug_id' => $drug->id,
        'medication_name' => $drug->name,
        'dose_quantity' => '1',
        'frequency' => 'Once daily',
        'duration' => '30 days',
        'quantity_to_dispense' => 30,
        'instructions' => 'Take with food',
    ]);

    // Create current consultation
    $currentCheckin = PatientCheckin::factory()->create([
        'patient_id' => $this->patient->id,
        'department_id' => $this->department->id,
    ]);
    $currentConsultation = Consultation::factory()->create([
        'patient_checkin_id' => $currentCheckin->id,
        'doctor_id' => $this->doctor->id,
        'status' => 'in_progress',
    ]);

    // Refill the prescription
    $response = $this->actingAs($this->doctor)
        ->post("/consultation/{$currentConsultation->id}/prescriptions/refill", [
            'prescription_ids' => [$previousPrescription->id],
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Check new prescription was created
    $newPrescription = Prescription::where('consultation_id', $currentConsultation->id)->first();
    expect($newPrescription)->not->toBeNull();
    expect($newPrescription->refilled_from_prescription_id)->toBe($previousPrescription->id);
    expect($newPrescription->drug_id)->toBe($drug->id);
    expect($newPrescription->medication_name)->toBe($drug->name);
    expect($newPrescription->frequency)->toBe('Once daily');
    expect($newPrescription->duration)->toBe('30 days');
    expect($newPrescription->status)->toBe('prescribed');
});

it('can bulk refill multiple prescriptions', function () {
    // Create previous consultation with multiple prescriptions
    $previousCheckin = PatientCheckin::factory()->create([
        'patient_id' => $this->patient->id,
        'department_id' => $this->department->id,
    ]);
    $previousConsultation = Consultation::factory()->create([
        'patient_checkin_id' => $previousCheckin->id,
        'doctor_id' => $this->doctor->id,
        'status' => 'completed',
    ]);

    $drug1 = Drug::factory()->create(['is_active' => true]);
    $drug2 = Drug::factory()->create(['is_active' => true]);

    $prescription1 = createPrescriptionWithoutEvents([
        'consultation_id' => $previousConsultation->id,
        'drug_id' => $drug1->id,
        'medication_name' => $drug1->name,
    ]);
    $prescription2 = createPrescriptionWithoutEvents([
        'consultation_id' => $previousConsultation->id,
        'drug_id' => $drug2->id,
        'medication_name' => $drug2->name,
    ]);

    // Create current consultation
    $currentCheckin = PatientCheckin::factory()->create([
        'patient_id' => $this->patient->id,
        'department_id' => $this->department->id,
    ]);
    $currentConsultation = Consultation::factory()->create([
        'patient_checkin_id' => $currentCheckin->id,
        'doctor_id' => $this->doctor->id,
        'status' => 'in_progress',
    ]);

    // Bulk refill
    $response = $this->actingAs($this->doctor)
        ->post("/consultation/{$currentConsultation->id}/prescriptions/refill", [
            'prescription_ids' => [$prescription1->id, $prescription2->id],
        ]);

    $response->assertRedirect();

    // Check both prescriptions were created
    $newPrescriptions = Prescription::where('consultation_id', $currentConsultation->id)->get();
    expect($newPrescriptions)->toHaveCount(2);
});

it('skips inactive drugs during refill', function () {
    $previousCheckin = PatientCheckin::factory()->create([
        'patient_id' => $this->patient->id,
        'department_id' => $this->department->id,
    ]);
    $previousConsultation = Consultation::factory()->create([
        'patient_checkin_id' => $previousCheckin->id,
        'doctor_id' => $this->doctor->id,
        'status' => 'completed',
    ]);

    $inactiveDrug = Drug::factory()->create(['is_active' => false]);
    $prescription = createPrescriptionWithoutEvents([
        'consultation_id' => $previousConsultation->id,
        'drug_id' => $inactiveDrug->id,
        'medication_name' => $inactiveDrug->name,
    ]);

    $currentCheckin = PatientCheckin::factory()->create([
        'patient_id' => $this->patient->id,
        'department_id' => $this->department->id,
    ]);
    $currentConsultation = Consultation::factory()->create([
        'patient_checkin_id' => $currentCheckin->id,
        'doctor_id' => $this->doctor->id,
        'status' => 'in_progress',
    ]);

    $response = $this->actingAs($this->doctor)
        ->post("/consultation/{$currentConsultation->id}/prescriptions/refill", [
            'prescription_ids' => [$prescription->id],
        ]);

    $response->assertRedirect();

    // No prescription should be created for inactive drug
    $newPrescriptions = Prescription::where('consultation_id', $currentConsultation->id)->get();
    expect($newPrescriptions)->toHaveCount(0);
});

it('can update an existing prescription', function () {
    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $this->patient->id,
        'department_id' => $this->department->id,
    ]);
    $consultation = Consultation::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'doctor_id' => $this->doctor->id,
        'status' => 'in_progress',
    ]);

    $drug = Drug::factory()->create(['is_active' => true]);
    $prescription = createPrescriptionWithoutEvents([
        'consultation_id' => $consultation->id,
        'drug_id' => $drug->id,
        'medication_name' => $drug->name,
        'frequency' => 'Once daily',
        'duration' => '7 days',
        'status' => 'prescribed',
    ]);

    $newDrug = Drug::factory()->create(['is_active' => true]);

    $response = $this->actingAs($this->doctor)
        ->patch("/consultation/{$consultation->id}/prescriptions/{$prescription->id}", [
            'drug_id' => $newDrug->id,
            'medication_name' => $newDrug->name,
            'dose_quantity' => '2',
            'frequency' => 'Twice daily (BID)',
            'duration' => '14 days',
            'quantity_to_dispense' => 56,
            'instructions' => 'Updated instructions',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $prescription->refresh();
    expect($prescription->drug_id)->toBe($newDrug->id);
    expect($prescription->frequency)->toBe('Twice daily (BID)');
    expect($prescription->duration)->toBe('14 days');
    expect($prescription->instructions)->toBe('Updated instructions');
});

it('cannot update a dispensed prescription', function () {
    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $this->patient->id,
        'department_id' => $this->department->id,
    ]);
    $consultation = Consultation::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'doctor_id' => $this->doctor->id,
        'status' => 'in_progress',
    ]);

    $drug = Drug::factory()->create(['is_active' => true]);
    $prescription = createPrescriptionWithoutEvents([
        'consultation_id' => $consultation->id,
        'drug_id' => $drug->id,
        'medication_name' => $drug->name,
        'status' => 'dispensed', // Already dispensed
    ]);

    $response = $this->actingAs($this->doctor)
        ->patch("/consultation/{$consultation->id}/prescriptions/{$prescription->id}", [
            'drug_id' => $drug->id,
            'medication_name' => $drug->name,
            'frequency' => 'Twice daily (BID)',
            'duration' => '14 days',
        ]);

    // Should redirect back with error (not forbidden, since user has permission but prescription is dispensed)
    $response->assertRedirect();
    $response->assertSessionHas('error');
});
