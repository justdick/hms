<?php

use App\Models\Bed;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\Ward;
use App\Models\WardRound;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('does not display labs tab when no lab orders exist', function () {
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
        ->where('admission.ward_rounds', [])
    );
});

it('displays labs tab when lab orders exist', function () {
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

    \App\Models\LabOrder::create([
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
    );
});

it('displays lab orders with correct status', function () {
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

    $labService = \App\Models\LabService::factory()->create();

    // Create lab orders with different statuses
    \App\Models\LabOrder::create([
        'orderable_type' => 'App\Models\WardRound',
        'orderable_id' => $wardRound->id,
        'lab_service_id' => $labService->id,
        'status' => 'pending',
        'priority' => 'routine',
        'ordered_at' => now(),
        'ordered_by' => $doctor->id,
    ]);

    \App\Models\LabOrder::create([
        'orderable_type' => 'App\Models\WardRound',
        'orderable_id' => $wardRound->id,
        'lab_service_id' => $labService->id,
        'status' => 'in_progress',
        'priority' => 'routine',
        'ordered_at' => now(),
        'ordered_by' => $doctor->id,
    ]);

    \App\Models\LabOrder::create([
        'orderable_type' => 'App\Models\WardRound',
        'orderable_id' => $wardRound->id,
        'lab_service_id' => $labService->id,
        'status' => 'completed',
        'priority' => 'routine',
        'ordered_at' => now(),
        'ordered_by' => $doctor->id,
    ]);

    $response = $this->get(route('wards.patients.show', [$ward, $admission]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/PatientShow')
        ->has('admission.ward_rounds.0.lab_orders', 3)
        ->where('admission.ward_rounds.0.lab_orders.0.status', 'pending')
        ->where('admission.ward_rounds.0.lab_orders.1.status', 'in_progress')
        ->where('admission.ward_rounds.0.lab_orders.2.status', 'completed')
    );
});

it('displays lab orders with priority levels', function () {
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

    $labService = \App\Models\LabService::factory()->create();

    \App\Models\LabOrder::create([
        'orderable_type' => 'App\Models\WardRound',
        'orderable_id' => $wardRound->id,
        'lab_service_id' => $labService->id,
        'status' => 'pending',
        'priority' => 'urgent',
        'ordered_at' => now(),
        'ordered_by' => $doctor->id,
    ]);

    $response = $this->get(route('wards.patients.show', [$ward, $admission]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/PatientShow')
        ->has('admission.ward_rounds.0.lab_orders', 1)
        ->where('admission.ward_rounds.0.lab_orders.0.priority', 'urgent')
    );
});

it('displays lab service details with lab orders', function () {
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
        'name' => 'Lipid Panel',
        'code' => 'LIPID',
    ]);

    \App\Models\LabOrder::create([
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
        ->has('admission.ward_rounds.0.lab_orders.0.lab_service')
        ->where('admission.ward_rounds.0.lab_orders.0.lab_service.name', 'Lipid Panel')
        ->where('admission.ward_rounds.0.lab_orders.0.lab_service.code', 'LIPID')
    );
});

it('displays lab results when completed', function () {
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

    $labService = \App\Models\LabService::factory()->create();

    \App\Models\LabOrder::create([
        'orderable_type' => 'App\Models\WardRound',
        'orderable_id' => $wardRound->id,
        'lab_service_id' => $labService->id,
        'status' => 'completed',
        'priority' => 'routine',
        'ordered_at' => now()->subDay(),
        'ordered_by' => $doctor->id,
        'result_values' => json_encode(['hemoglobin' => '14.5', 'wbc' => '7.2']),
        'result_notes' => 'All values within normal range',
    ]);

    $response = $this->get(route('wards.patients.show', [$ward, $admission]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/PatientShow')
        ->has('admission.ward_rounds.0.lab_orders', 1)
        ->where('admission.ward_rounds.0.lab_orders.0.status', 'completed')
        ->has('admission.ward_rounds.0.lab_orders.0.result_values')
        ->where('admission.ward_rounds.0.lab_orders.0.result_notes', 'All values within normal range')
    );
});
