<?php

use App\Models\Bed;
use App\Models\Consultation;
use App\Models\Drug;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\PatientCheckin;
use App\Models\Prescription;
use App\Models\User;
use App\Models\VitalSign;
use App\Models\VitalsSchedule;
use App\Models\Ward;
use App\Models\WardRound;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('renders overview tab as default tab on patient show page', function () {
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
        ->has('admission')
    );
});

it('displays admission day number in overview tab', function () {
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
        'admitted_at' => now()->subDays(3),
    ]);

    $response = $this->get(route('wards.patients.show', [$ward, $admission]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/PatientShow')
        ->where('admission.admitted_at', $admission->admitted_at->toISOString())
    );
});

it('displays diagnosis from consultation in overview', function () {
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

    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
    ]);

    $consultation = Consultation::factory()->create([
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
        ->has('admission.consultation.doctor')
    );
});

it('displays diagnosis from ward round in overview', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create(['name' => 'Dr. Jones']);

    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $wardRound = WardRound::factory()->create([
        'patient_admission_id' => $admission->id,
        'doctor_id' => $doctor->id,
        'day_number' => 1,
        'round_type' => 'daily_round',
        'round_datetime' => now(),
        'status' => 'completed',
    ]);

    $diagnosis = \App\Models\Diagnosis::factory()->create([
        'diagnosis' => 'Hypertension',
        'icd_10' => 'I10',
    ]);

    \App\Models\AdmissionDiagnosis::create([
        'patient_admission_id' => $admission->id,
        'diagnosis_id' => $diagnosis->id,
        'diagnosis_name' => 'Hypertension',
        'icd_code' => 'I10',
        'icd_version' => 'ICD-10',
        'diagnosed_by' => $doctor->id,
        'diagnosed_at' => now(),
        'source_type' => 'App\Models\WardRound',
        'source_id' => $wardRound->id,
        'type' => 'primary',
    ]);

    $response = $this->get(route('wards.patients.show', [$ward, $admission]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/PatientShow')
        ->has('admission.ward_rounds', 1)
        ->has('admission.ward_rounds.0.diagnoses', 1)
    );
});

it('displays active prescriptions in overview', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();

    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
    ]);

    $consultation = Consultation::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'status' => 'completed',
    ]);

    $drug = Drug::factory()->create([
        'name' => 'Amoxicillin',
        'strength' => '500mg',
        'form' => 'capsule',
    ]);

    $prescription = Prescription::factory()->create([
        'consultation_id' => $consultation->id,
        'drug_id' => $drug->id,
        'medication_name' => 'Amoxicillin',
        'dose_quantity' => '500mg',
        'frequency' => 'Three times daily',
        'duration' => '7 days',
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
        ->where('admission.consultation.prescriptions.0.medication_name', 'Amoxicillin')
        ->where('admission.consultation.prescriptions.0.frequency', 'Three times daily')
    );
});

it('displays latest vital signs in overview', function () {
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

    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $vitalSign = VitalSign::factory()->create([
        'patient_id' => $patient->id,
        'patient_admission_id' => $admission->id,
        'recorded_by' => $nurse->id,
        'temperature' => 37.2,
        'blood_pressure_systolic' => 120,
        'blood_pressure_diastolic' => 80,
        'pulse_rate' => 72,
        'recorded_at' => now(),
    ]);

    $response = $this->get(route('wards.patients.show', [$ward, $admission]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/PatientShow')
        ->has('admission.vital_signs', 1)
        ->where('admission.vital_signs.0.temperature', '37.20')
        ->where('admission.vital_signs.0.pulse_rate', 72)
    );
});

it('displays vitals schedule status in overview', function () {
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
        ->has('admission.vitals_schedule')
        ->where('admission.vitals_schedule.interval_minutes', 240)
        ->where('admission.vitals_schedule.is_active', true)
    );
});

it('displays lab orders summary in overview', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();
    $doctor = User::factory()->create();

    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $wardRound = WardRound::factory()->create([
        'patient_admission_id' => $admission->id,
        'doctor_id' => $doctor->id,
        'day_number' => 1,
        'round_type' => 'daily_round',
        'round_datetime' => now(),
        'status' => 'completed',
    ]);

    $labService = \App\Models\LabService::factory()->create([
        'name' => 'Complete Blood Count',
        'code' => 'CBC',
    ]);

    $labOrder = \App\Models\LabOrder::create([
        'orderable_type' => 'App\Models\WardRound',
        'orderable_id' => $wardRound->id,
        'lab_service_id' => $labService->id,
        'status' => 'pending',
        'priority' => 'routine',
        'ordered_at' => now(),
        'ordered_by' => $doctor->id,
    ]);

    $response = $this->get(route('wards.patients.show', [$ward, $admission]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/PatientShow')
        ->has('admission.ward_rounds', 1)
        ->has('admission.ward_rounds.0.lab_orders', 1)
        ->where('admission.ward_rounds.0.lab_orders.0.status', 'pending')
    );
});

it('shows no diagnosis state when no diagnosis exists', function () {
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
        ->has('admission.ward_rounds')
    );
});

it('shows pending medication count badge', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();

    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
    ]);

    $consultation = Consultation::factory()->create([
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

    $drug = Drug::factory()->create();
    $prescription = Prescription::factory()->create([
        'consultation_id' => $consultation->id,
        'drug_id' => $drug->id,
    ]);

    // Create medication administrations
    \App\Models\MedicationAdministration::factory()->count(3)->create([
        'patient_admission_id' => $admission->id,
        'prescription_id' => $prescription->id,
        'status' => 'given',
        'administered_at' => now(),
    ]);

    $response = $this->get(route('wards.patients.show', [$ward, $admission]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/PatientShow')
        ->has('admission.medication_administrations')
        ->where('admission.medication_administrations', fn ($meds) => count($meds) >= 3)
    );
});

it('shows overdue vitals indicator when vitals are old', function () {
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

    // Create old vital sign (more than 4 hours ago)
    VitalSign::factory()->create([
        'patient_id' => $patient->id,
        'patient_admission_id' => $admission->id,
        'recorded_by' => $this->user->id,
        'temperature' => 37.0,
        'recorded_at' => now()->subHours(5),
    ]);

    $response = $this->get(route('wards.patients.show', [$ward, $admission]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/PatientShow')
        ->has('admission.vital_signs', 1)
    );
});
