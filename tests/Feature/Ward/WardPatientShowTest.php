<?php

use App\Models\Bed;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\VitalsSchedule;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('includes active vitals schedule status in patient show response', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();
    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 240,
        'next_due_at' => now()->addHours(2),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $response = $this->get(route('wards.patients.show', [$ward, $admission]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/PatientShow')
        ->has('vitalsScheduleStatus')
        ->where('vitalsScheduleStatus.status', 'upcoming')
        ->where('vitalsScheduleStatus.interval_minutes', 240)
        ->where('vitalsScheduleStatus.is_active', true)
        ->has('vitalsScheduleStatus.next_due_at')
        ->has('vitalsScheduleStatus.time_until_due_minutes')
    );
});

it('includes schedule history in patient show response', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();
    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    // Create multiple schedules (history)
    $oldSchedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 120,
        'is_active' => false,
        'created_by' => $this->user->id,
        'created_at' => now()->subDays(2),
    ]);

    $currentSchedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 240,
        'next_due_at' => now()->addHours(1),
        'is_active' => true,
        'created_by' => $this->user->id,
        'created_at' => now()->subDay(),
    ]);

    $response = $this->get(route('wards.patients.show', [$ward, $admission]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/PatientShow')
        ->has('scheduleHistory', 2)
        ->where('scheduleHistory.0.id', $currentSchedule->id)
        ->where('scheduleHistory.0.interval_minutes', 240)
        ->where('scheduleHistory.0.is_active', true)
        ->has('scheduleHistory.0.created_by')
        ->has('scheduleHistory.0.status')
        ->where('scheduleHistory.1.id', $oldSchedule->id)
        ->where('scheduleHistory.1.is_active', false)
    );
});

it('returns null vitals schedule status when no active schedule exists', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();
    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $response = $this->get(route('wards.patients.show', [$ward, $admission]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/PatientShow')
        ->where('vitalsScheduleStatus', null)
        ->has('scheduleHistory')
    );
});

it('includes next due time in schedule status', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();
    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $nextDueAt = now()->addHours(3);
    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 180,
        'next_due_at' => $nextDueAt,
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $response = $this->get(route('wards.patients.show', [$ward, $admission]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/PatientShow')
        ->has('vitalsScheduleStatus.next_due_at')
        ->where('vitalsScheduleStatus.status', 'upcoming')
    );
});

it('shows due status when vitals are due', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();
    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 240,
        'next_due_at' => now()->subMinutes(5),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $response = $this->get(route('wards.patients.show', [$ward, $admission]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/PatientShow')
        ->where('vitalsScheduleStatus.status', 'due')
        ->where('vitalsScheduleStatus.time_until_due_minutes', 0)
    );
});

it('shows overdue status when vitals are overdue', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();
    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 240,
        'next_due_at' => now()->subMinutes(20),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $response = $this->get(route('wards.patients.show', [$ward, $admission]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/PatientShow')
        ->where('vitalsScheduleStatus.status', 'overdue')
        ->has('vitalsScheduleStatus.time_overdue_minutes')
    );
});

it('includes consultation data with doctor in patient show response', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create(['name' => 'Dr. Smith']);

    $checkin = \App\Models\PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
    ]);

    $consultation = \App\Models\Consultation::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'doctor_id' => $doctor->id,
        'status' => 'completed',
    ]);

    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'consultation_id' => $consultation->id,
        'status' => 'admitted',
    ]);

    $response = $this->get(route('wards.patients.show', [$ward, $admission]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/PatientShow')
        ->has('admission.consultation')
        ->where('admission.consultation.id', $consultation->id)
        ->has('admission.consultation.doctor')
        ->where('admission.consultation.doctor.name', 'Dr. Smith')
    );
});

it('includes consultation vitals in patient show response', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();
    $nurse = User::factory()->create(['name' => 'Nurse Jane']);

    $checkin = \App\Models\PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
    ]);

    $vitalSign = \App\Models\VitalSign::factory()->create([
        'patient_id' => $patient->id,
        'patient_checkin_id' => $checkin->id,
        'recorded_by' => $nurse->id,
        'temperature' => 37.5,
        'blood_pressure_systolic' => 120,
        'blood_pressure_diastolic' => 80,
        'pulse_rate' => 75,
    ]);

    $consultation = \App\Models\Consultation::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'status' => 'completed',
    ]);

    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'consultation_id' => $consultation->id,
        'status' => 'admitted',
    ]);

    $response = $this->get(route('wards.patients.show', [$ward, $admission]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/PatientShow')
        ->has('admission.consultation.patient_checkin')
        ->has('admission.consultation.patient_checkin.vital_signs', 1)
        ->where('admission.consultation.patient_checkin.vital_signs.0.temperature', '37.50')
        ->where('admission.consultation.patient_checkin.vital_signs.0.blood_pressure_systolic', '120.00')
        ->where('admission.consultation.patient_checkin.vital_signs.0.pulse_rate', 75)
        ->has('admission.consultation.patient_checkin.vital_signs.0.recorded_by')
        ->where('admission.consultation.patient_checkin.vital_signs.0.recorded_by.name', 'Nurse Jane')
    );
});

it('includes consultation prescriptions with drug details in patient show response', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();

    $checkin = \App\Models\PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
    ]);

    $consultation = \App\Models\Consultation::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'status' => 'completed',
    ]);

    $drug = \App\Models\Drug::factory()->create([
        'name' => 'Paracetamol',
        'strength' => '500mg',
        'form' => 'tablet',
    ]);

    $prescription = \App\Models\Prescription::factory()->create([
        'consultation_id' => $consultation->id,
        'drug_id' => $drug->id,
        'medication_name' => 'Paracetamol',
        'dose_quantity' => '500mg',
        'frequency' => 'Three times daily',
        'duration' => '5 days',
    ]);

    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'consultation_id' => $consultation->id,
        'status' => 'admitted',
    ]);

    $response = $this->get(route('wards.patients.show', [$ward, $admission]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/PatientShow')
        ->has('admission.consultation.prescriptions', 1)
        ->where('admission.consultation.prescriptions.0.medication_name', 'Paracetamol')
        ->where('admission.consultation.prescriptions.0.dose_quantity', '500mg')
        ->where('admission.consultation.prescriptions.0.frequency', 'Three times daily')
        ->has('admission.consultation.prescriptions.0.drug')
        ->where('admission.consultation.prescriptions.0.drug.name', 'Paracetamol')
    );
});

it('handles admissions without consultations correctly', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();

    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'consultation_id' => null,
        'status' => 'admitted',
    ]);

    $response = $this->get(route('wards.patients.show', [$ward, $admission]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/PatientShow')
        ->where('admission.consultation', null)
    );
});

it('includes consultation lab orders in patient show response', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();

    $checkin = \App\Models\PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
    ]);

    $consultation = \App\Models\Consultation::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'status' => 'completed',
    ]);

    $labService = \App\Models\LabService::factory()->create([
        'name' => 'Complete Blood Count',
        'code' => 'CBC001',
    ]);

    $labOrder = \App\Models\LabOrder::factory()->create([
        'orderable_type' => \App\Models\Consultation::class,
        'orderable_id' => $consultation->id,
        'lab_service_id' => $labService->id,
        'status' => 'pending',
        'priority' => 'routine',
    ]);

    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'consultation_id' => $consultation->id,
        'status' => 'admitted',
    ]);

    $response = $this->get(route('wards.patients.show', [$ward, $admission]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/PatientShow')
        ->has('admission.consultation.lab_orders', 1)
        ->where('admission.consultation.lab_orders.0.status', 'pending')
        ->has('admission.consultation.lab_orders.0.lab_service')
        ->where('admission.consultation.lab_orders.0.lab_service.name', 'Complete Blood Count')
    );
});

it('includes consultation diagnoses in patient show response', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();

    $checkin = \App\Models\PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
    ]);

    $consultation = \App\Models\Consultation::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'status' => 'completed',
    ]);

    $diagnosis = \App\Models\Diagnosis::factory()->create([
        'diagnosis' => 'Malaria',
        'icd_10' => 'B50.9',
    ]);

    \App\Models\ConsultationDiagnosis::create([
        'consultation_id' => $consultation->id,
        'diagnosis_id' => $diagnosis->id,
        'type' => 'principal',
    ]);

    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'consultation_id' => $consultation->id,
        'status' => 'admitted',
    ]);

    $response = $this->get(route('wards.patients.show', [$ward, $admission]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/PatientShow')
        ->has('admission.consultation.diagnoses', 1)
        ->where('admission.consultation.diagnoses.0.type', 'principal')
        ->has('admission.consultation.diagnoses.0.diagnosis')
        ->where('admission.consultation.diagnoses.0.diagnosis.diagnosis', 'Malaria')
        ->where('admission.consultation.diagnoses.0.diagnosis.icd_10', 'B50.9')
    );
});
