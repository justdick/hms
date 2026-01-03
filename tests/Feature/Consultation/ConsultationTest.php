<?php

use App\Models\Consultation;
use App\Models\Department;
use App\Models\Diagnosis;
use App\Models\LabService;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->doctor = User::factory()->create();

    // Create admin role and assign it to doctor
    $adminRole = Role::create(['name' => 'Admin']);
    $this->doctor->assignRole($adminRole);

    $this->department = Department::factory()->create();
    $this->patient = Patient::factory()->create();

    // Associate doctor with department
    $this->department->users()->attach($this->doctor->id);

    $this->patientCheckin = PatientCheckin::factory()->create([
        'patient_id' => $this->patient->id,
        'department_id' => $this->department->id,
        'status' => 'awaiting_consultation',
        'checked_in_at' => now()->subHours(2), // Explicitly set to today, 2 hours ago
    ]);
});

describe('Consultation Dashboard', function () {
    it('shows patients awaiting consultation for doctor\'s department', function () {
        $response = $this->actingAs($this->doctor)->get('/consultation');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Consultation/Index')
                ->has('awaitingConsultation', 1)
                ->where('awaitingConsultation.0.id', $this->patientCheckin->id)
                ->has('departments')
                ->has('filters')
            );
    });

    it('does not show patients from other departments', function () {
        $otherDepartment = Department::factory()->create();
        $otherPatient = Patient::factory()->create();
        PatientCheckin::factory()->create([
            'patient_id' => $otherPatient->id,
            'department_id' => $otherDepartment->id,
            'status' => 'awaiting_consultation',
            'checked_in_at' => now()->subHours(1), // Explicitly set to today, 1 hour ago
        ]);

        $response = $this->actingAs($this->doctor)->get('/consultation');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('awaitingConsultation', 1) // Only shows our department's patient
                ->where('awaitingConsultation.0.patient.id', $this->patient->id)
            );
    });

    it('shows active consultations for the doctor', function () {
        $consultation = Consultation::factory()->create([
            'patient_checkin_id' => $this->patientCheckin->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->doctor)->get('/consultation');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('activeConsultations', 1)
                ->where('activeConsultations.0.id', $consultation->id)
            );
    });

    it('filters by department when department_id is provided', function () {
        $response = $this->actingAs($this->doctor)->get('/consultation?department_id='.$this->department->id);

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('awaitingConsultation', 1)
                ->where('filters.department_id', (string) $this->department->id)
            );
    });

    it('filters by search when search query is provided', function () {
        $response = $this->actingAs($this->doctor)->get('/consultation?search='.$this->patient->first_name);

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('awaitingConsultation', 1)
                ->where('filters.search', $this->patient->first_name)
            );
    });
});

describe('Starting Consultation', function () {
    it('can start a consultation for a patient in doctor\'s department', function () {
        $response = $this->actingAs($this->doctor)->post('/consultation', [
            'patient_checkin_id' => $this->patientCheckin->id,
            'chief_complaint' => 'Chest pain and shortness of breath',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('consultations', [
            'patient_checkin_id' => $this->patientCheckin->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'in_progress',
            'chief_complaint' => 'Chest pain and shortness of breath',
        ]);

        // Should update patient check-in status
        $this->patientCheckin->refresh();
        expect($this->patientCheckin->status)->toBe('in_consultation');
        expect($this->patientCheckin->consultation_started_at)->not->toBeNull();
    });

    it('cannot start consultation for patient in different department', function () {
        $otherDepartment = Department::factory()->create();
        $otherPatientCheckin = PatientCheckin::factory()->create([
            'patient_id' => Patient::factory()->create()->id,
            'department_id' => $otherDepartment->id,
            'status' => 'awaiting_consultation',
        ]);

        $response = $this->actingAs($this->doctor)->post('/consultation', [
            'patient_checkin_id' => $otherPatientCheckin->id,
        ]);

        $response->assertForbidden();
    });
});

describe('Updating Consultation', function () {
    beforeEach(function () {
        $this->consultation = Consultation::factory()->create([
            'patient_checkin_id' => $this->patientCheckin->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'in_progress',
        ]);
    });

    it('can update SOAP notes', function () {
        $response = $this->actingAs($this->doctor)->patch("/consultation/{$this->consultation->id}", [
            'subjective_notes' => 'Patient reports chest pain for 2 hours',
            'objective_notes' => 'BP 140/90, HR 85, no acute distress',
            'assessment_notes' => 'Possible angina, rule out MI',
            'plan_notes' => 'ECG, cardiac enzymes, aspirin 325mg',
            'follow_up_date' => '2025-10-01',
        ]);

        $response->assertRedirect();

        $this->consultation->refresh();
        expect($this->consultation->subjective_notes)->toBe('Patient reports chest pain for 2 hours');
        expect($this->consultation->objective_notes)->toBe('BP 140/90, HR 85, no acute distress');
        expect($this->consultation->assessment_notes)->toBe('Possible angina, rule out MI');
        expect($this->consultation->plan_notes)->toBe('ECG, cardiac enzymes, aspirin 325mg');
        expect($this->consultation->follow_up_date->format('Y-m-d'))->toBe('2025-10-01');
    });

    it('allows other doctors in same department to update consultation', function () {
        $anotherDoctor = User::factory()->create();
        $this->department->users()->attach($anotherDoctor->id);

        $response = $this->actingAs($anotherDoctor)->patch("/consultation/{$this->consultation->id}", [
            'subjective_notes' => 'Updated by another doctor',
        ]);

        $response->assertRedirect();

        $this->consultation->refresh();
        expect($this->consultation->subjective_notes)->toBe('Updated by another doctor');
    });

    it('prevents doctors from other departments from updating consultation', function () {
        $otherDoctor = User::factory()->create();

        $response = $this->actingAs($otherDoctor)->patch("/consultation/{$this->consultation->id}", [
            'subjective_notes' => 'Should not be allowed',
        ]);

        $response->assertForbidden();
    });
});

describe('Completing Consultation', function () {
    beforeEach(function () {
        $this->consultation = Consultation::factory()->create([
            'patient_checkin_id' => $this->patientCheckin->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'in_progress',
        ]);
    });

    it('can complete a consultation and update status', function () {
        $response = $this->actingAs($this->doctor)->post("/consultation/{$this->consultation->id}/complete");

        $response->assertRedirect()
            ->assertSessionHas('success', 'Consultation completed successfully.');

        // Should update consultation status
        $this->consultation->refresh();
        expect($this->consultation->status)->toBe('completed');
        expect($this->consultation->completed_at)->not->toBeNull();

        // Should update patient check-in status
        $this->patientCheckin->refresh();
        expect($this->patientCheckin->status)->toBe('completed');
        expect($this->patientCheckin->consultation_completed_at)->not->toBeNull();
    });

    it('includes lab orders when completing consultation', function () {
        $labService = LabService::factory()->create([
            'name' => 'Complete Blood Count (CBC)',
            'price' => 150.00,
        ]);

        // Add lab order to consultation
        $this->consultation->labOrders()->create([
            'lab_service_id' => $labService->id,
            'ordered_by' => $this->doctor->id,
            'ordered_at' => now(),
            'status' => 'ordered',
        ]);

        $response = $this->actingAs($this->doctor)->post("/consultation/{$this->consultation->id}/complete");

        $response->assertRedirect();

        // Should update consultation status
        $this->consultation->refresh();
        expect($this->consultation->status)->toBe('completed');

        // Lab order should still exist
        expect($this->consultation->labOrders()->count())->toBe(1);
    });
});

describe('Adding Diagnoses', function () {
    beforeEach(function () {
        $this->consultation = Consultation::factory()->create([
            'patient_checkin_id' => $this->patientCheckin->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'in_progress',
        ]);
    });

    it('can add a diagnosis to consultation', function () {
        $diagnosis = Diagnosis::factory()->create([
            'code' => 'I10',
            'diagnosis' => 'Essential hypertension',
        ]);

        $response = $this->actingAs($this->doctor)
            ->post("/consultation/{$this->consultation->id}/diagnoses", [
                'diagnosis_id' => $diagnosis->id,
                'type' => 'principal',
            ]);

        $response->assertRedirect()
            ->assertSessionHas('success', 'Diagnosis added successfully.');

        $this->assertDatabaseHas('consultation_diagnoses', [
            'consultation_id' => $this->consultation->id,
            'diagnosis_id' => $diagnosis->id,
            'type' => 'principal',
        ]);
    });

    it('can add multiple diagnoses with different types', function () {
        $diagnosis1 = Diagnosis::factory()->create([
            'code' => 'I20.9',
            'diagnosis' => 'Angina pectoris, unspecified',
        ]);

        $diagnosis2 = Diagnosis::factory()->create([
            'code' => 'I10',
            'diagnosis' => 'Essential hypertension',
        ]);

        // Add first principal diagnosis
        $this->consultation->diagnoses()->create([
            'diagnosis_id' => $diagnosis1->id,
            'type' => 'principal',
        ]);

        // Add second provisional diagnosis
        $response = $this->actingAs($this->doctor)
            ->post("/consultation/{$this->consultation->id}/diagnoses", [
                'diagnosis_id' => $diagnosis2->id,
                'type' => 'provisional',
            ]);

        $response->assertRedirect();

        // Should have both diagnoses
        $this->assertDatabaseHas('consultation_diagnoses', [
            'consultation_id' => $this->consultation->id,
            'diagnosis_id' => $diagnosis1->id,
            'type' => 'principal',
        ]);

        $this->assertDatabaseHas('consultation_diagnoses', [
            'consultation_id' => $this->consultation->id,
            'diagnosis_id' => $diagnosis2->id,
            'type' => 'provisional',
        ]);
    });
});

describe('Ordering Lab Tests', function () {
    beforeEach(function () {
        $this->consultation = Consultation::factory()->create([
            'patient_checkin_id' => $this->patientCheckin->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'in_progress',
        ]);

        $this->labService = LabService::factory()->create([
            'name' => 'Complete Blood Count (CBC)',
            'code' => 'CBC001',
            'price' => 150.00,
            'is_active' => true,
        ]);
    });

    it('can order a lab test', function () {
        $response = $this->actingAs($this->doctor)
            ->postJson("/consultation/{$this->consultation->id}/lab-orders", [
                'lab_service_id' => $this->labService->id,
                'priority' => 'urgent',
                'special_instructions' => 'Fasting sample required',
            ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Lab test ordered successfully.',
            ]);

        $this->assertDatabaseHas('lab_orders', [
            'consultation_id' => $this->consultation->id,
            'lab_service_id' => $this->labService->id,
            'ordered_by' => $this->doctor->id,
            'priority' => 'urgent',
            'special_instructions' => 'Fasting sample required',
            'status' => 'ordered',
        ]);
    });

    it('prevents duplicate lab orders for same test', function () {
        // Create existing order
        $this->consultation->labOrders()->create([
            'lab_service_id' => $this->labService->id,
            'ordered_by' => $this->doctor->id,
            'ordered_at' => now(),
            'status' => 'ordered',
        ]);

        $response = $this->actingAs($this->doctor)
            ->postJson("/consultation/{$this->consultation->id}/lab-orders", [
                'lab_service_id' => $this->labService->id,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'This lab test has already been ordered for this consultation.',
            ]);
    });

    it('prevents ordering inactive lab services', function () {
        $this->labService->update(['is_active' => false]);

        $response = $this->actingAs($this->doctor)
            ->postJson("/consultation/{$this->consultation->id}/lab-orders", [
                'lab_service_id' => $this->labService->id,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'This lab service is not currently available.',
            ]);
    });
});
