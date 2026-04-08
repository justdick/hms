<?php

use App\Models\Consultation;
use App\Models\InsuranceClaim;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\PatientCheckin;
use App\Models\PatientInsurance;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);

    $this->ward = Ward::factory()->create();

    // Create NHIA provider and plan
    $this->nhiaProvider = InsuranceProvider::factory()->nhis()->create();
    $this->nhiaPlan = InsurancePlan::factory()->create([
        'insurance_provider_id' => $this->nhiaProvider->id,
    ]);
});

/**
 * Helper to create an admission with proper checkin → consultation → admission chain.
 */
function createAdmission(Ward $ward, Patient $patient, ?string $claimCheckCode = null): array
{
    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'claim_check_code' => $claimCheckCode,
        'service_date' => now()->toDateString(),
    ]);

    $consultation = Consultation::factory()->create([
        'patient_checkin_id' => $checkin->id,
    ]);

    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'ward_id' => $ward->id,
        'consultation_id' => $consultation->id,
        'status' => 'admitted',
        'admitted_at' => now(),
    ]);

    return [$checkin, $consultation, $admission];
}

it('counts NHIA admissions based on claim existence, not patient insurance profile', function () {
    // Patient 1: NHIA insurance, checked in WITH CCC (has claim)
    $patient1 = Patient::factory()->create();
    $pi1 = PatientInsurance::factory()->create([
        'patient_id' => $patient1->id,
        'insurance_plan_id' => $this->nhiaPlan->id,
        'status' => 'active',
        'coverage_start_date' => now()->subYear(),
        'coverage_end_date' => now()->addYear(),
    ]);
    [$checkin1] = createAdmission($this->ward, $patient1, '12345');
    InsuranceClaim::factory()->create([
        'patient_id' => $patient1->id,
        'patient_insurance_id' => $pi1->id,
        'patient_checkin_id' => $checkin1->id,
        'status' => 'pending_vetting',
    ]);

    // Patient 2: NHIA insurance, checked in WITHOUT CCC (no claim — cash)
    $patient2 = Patient::factory()->create();
    PatientInsurance::factory()->create([
        'patient_id' => $patient2->id,
        'insurance_plan_id' => $this->nhiaPlan->id,
        'status' => 'active',
        'coverage_start_date' => now()->subYear(),
        'coverage_end_date' => now()->addYear(),
    ]);
    createAdmission($this->ward, $patient2);

    // Filter by NHIA — should only return patient1 (has claim)
    $response = $this->get(route('wards.show', [
        'ward' => $this->ward,
        'insurance_type' => 'nhia',
        'status' => 'admitted',
        'date_from' => now()->startOfMonth()->toDateString(),
        'date_to' => now()->toDateString(),
    ]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/Show')
        ->where('admissions.total', 1)
    );
});

it('counts non-NHIA admissions including NHIA patients checked in as cash', function () {
    // Patient with NHIA insurance, checked in as cash (no claim)
    $patient = Patient::factory()->create();
    PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'insurance_plan_id' => $this->nhiaPlan->id,
        'status' => 'active',
        'coverage_start_date' => now()->subYear(),
        'coverage_end_date' => now()->addYear(),
    ]);
    createAdmission($this->ward, $patient);

    // Filter by non-NHIA — should include this patient (no claim)
    $response = $this->get(route('wards.show', [
        'ward' => $this->ward,
        'insurance_type' => 'non_nhia',
        'status' => 'admitted',
        'date_from' => now()->startOfMonth()->toDateString(),
        'date_to' => now()->toDateString(),
    ]));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/Show')
        ->where('admissions.total', 1)
    );
});

it('shows correct NHIA count in ward stats based on claim existence', function () {
    // Patient 1: has claim (NHIA)
    $patient1 = Patient::factory()->create();
    $pi1 = PatientInsurance::factory()->create([
        'patient_id' => $patient1->id,
        'insurance_plan_id' => $this->nhiaPlan->id,
        'status' => 'active',
        'coverage_start_date' => now()->subYear(),
        'coverage_end_date' => now()->addYear(),
    ]);
    [$checkin1] = createAdmission($this->ward, $patient1, '11111');
    InsuranceClaim::factory()->create([
        'patient_id' => $patient1->id,
        'patient_insurance_id' => $pi1->id,
        'patient_checkin_id' => $checkin1->id,
    ]);

    // Patient 2: no claim (cash, even though has NHIA)
    $patient2 = Patient::factory()->create();
    PatientInsurance::factory()->create([
        'patient_id' => $patient2->id,
        'insurance_plan_id' => $this->nhiaPlan->id,
        'status' => 'active',
        'coverage_start_date' => now()->subYear(),
        'coverage_end_date' => now()->addYear(),
    ]);
    createAdmission($this->ward, $patient2);

    $response = $this->get(route('wards.show', $this->ward));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/Show')
        ->where('stats.total_patients', 2)
        ->where('stats.nhia_patients', 1)
        ->where('stats.non_nhia_patients', 1)
    );
});
