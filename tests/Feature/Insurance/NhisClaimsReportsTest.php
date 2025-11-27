<?php

/**
 * Feature Tests for NHIS Claims Reports
 *
 * Tests the NHIS claims reports functionality including:
 * - Claims summary report (Requirement 18.1)
 * - Outstanding claims report (Requirement 18.2)
 * - Rejection analysis report (Requirement 18.3)
 * - Tariff coverage report (Requirement 18.4)
 * - Excel export functionality (Requirement 18.5)
 */

use App\Models\Drug;
use App\Models\InsuranceClaim;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\LabService;
use App\Models\NhisItemMapping;
use App\Models\NhisTariff;
use App\Models\Patient;
use App\Models\PatientInsurance;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    InsuranceClaim::query()->delete();
    NhisItemMapping::query()->delete();
    Permission::firstOrCreate(['name' => 'insurance.view-reports']);
});

describe('Claims Summary Report', function () {
    it('returns claims summary data for authenticated user with permission', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.view-reports');

        $provider = InsuranceProvider::factory()->create();
        $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
        $patient = Patient::factory()->create();
        $patientInsurance = PatientInsurance::factory()->create([
            'patient_id' => $patient->id,
            'insurance_plan_id' => $plan->id,
        ]);

        InsuranceClaim::factory()->count(3)->create([
            'patient_id' => $patient->id,
            'patient_insurance_id' => $patientInsurance->id,
            'status' => 'pending_vetting',
            'date_of_attendance' => now(),
            'total_claim_amount' => 100.00,
        ]);

        // Act - use JSON request to test the data
        $response = $this->actingAs($user)
            ->getJson(route('admin.insurance.reports.claims-summary'));

        // Assert
        $response->assertOk();
        $data = $response->json('data');
        expect($data['total_claims'])->toBe(3);
        expect((float) $data['total_claimed_amount'])->toEqual(300.0);
    });

    it('filters claims by date range', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.view-reports');

        $provider = InsuranceProvider::factory()->create();
        $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
        $patient = Patient::factory()->create();
        $patientInsurance = PatientInsurance::factory()->create([
            'patient_id' => $patient->id,
            'insurance_plan_id' => $plan->id,
        ]);

        // Create claims in different periods
        InsuranceClaim::factory()->create([
            'patient_id' => $patient->id,
            'patient_insurance_id' => $patientInsurance->id,
            'date_of_attendance' => now()->subMonth(),
            'total_claim_amount' => 100.00,
        ]);

        InsuranceClaim::factory()->create([
            'patient_id' => $patient->id,
            'patient_insurance_id' => $patientInsurance->id,
            'date_of_attendance' => now(),
            'total_claim_amount' => 200.00,
        ]);

        // Act: Query only current month
        $response = $this->actingAs($user)
            ->getJson(route('admin.insurance.reports.claims-summary', [
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->endOfMonth()->toDateString(),
            ]));

        // Assert
        $response->assertOk();
        $data = $response->json('data');
        expect($data['total_claims'])->toBe(1);
        expect((float) $data['total_claimed_amount'])->toEqual(200.0);
    });

    it('denies access to users without permission', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('admin.insurance.reports.claims-summary'));

        $response->assertForbidden();
    });
});

describe('Outstanding Claims Report', function () {
    it('returns outstanding claims data', function () {
        // Clear cache to ensure fresh data
        \Illuminate\Support\Facades\Cache::flush();

        $user = User::factory()->create();
        $user->givePermissionTo('insurance.view-reports');

        $provider = InsuranceProvider::factory()->create();
        $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
        $patient = Patient::factory()->create();
        $patientInsurance = PatientInsurance::factory()->create([
            'patient_id' => $patient->id,
            'insurance_plan_id' => $plan->id,
        ]);

        InsuranceClaim::factory()->create([
            'patient_id' => $patient->id,
            'patient_insurance_id' => $patientInsurance->id,
            'status' => 'submitted',
            'total_claim_amount' => 500.00,
            'submitted_at' => now()->subDays(15),
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('admin.insurance.reports.outstanding-claims'));

        $response->assertOk();
        $data = $response->json('data');

        // Verify the structure and that we have claims
        expect($data['total_claims'])->toBe(1);
        expect($data)->toHaveKey('total_outstanding');
        expect($data)->toHaveKey('aging_analysis');
        expect($data)->toHaveKey('by_provider');
    });

    it('filters by provider', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.view-reports');

        $provider1 = InsuranceProvider::factory()->create();
        $provider2 = InsuranceProvider::factory()->create();

        $plan1 = InsurancePlan::factory()->create(['insurance_provider_id' => $provider1->id]);
        $plan2 = InsurancePlan::factory()->create(['insurance_provider_id' => $provider2->id]);

        $patient = Patient::factory()->create();

        $patientInsurance1 = PatientInsurance::factory()->create([
            'patient_id' => $patient->id,
            'insurance_plan_id' => $plan1->id,
        ]);

        $patientInsurance2 = PatientInsurance::factory()->create([
            'patient_id' => $patient->id,
            'insurance_plan_id' => $plan2->id,
        ]);

        InsuranceClaim::factory()->create([
            'patient_id' => $patient->id,
            'patient_insurance_id' => $patientInsurance1->id,
            'status' => 'submitted',
            'total_claim_amount' => 300.00,
            'submitted_at' => now()->subDays(10),
        ]);

        InsuranceClaim::factory()->create([
            'patient_id' => $patient->id,
            'patient_insurance_id' => $patientInsurance2->id,
            'status' => 'submitted',
            'total_claim_amount' => 400.00,
            'submitted_at' => now()->subDays(10),
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('admin.insurance.reports.outstanding-claims', [
                'provider_id' => $provider1->id,
            ]));

        $response->assertOk();
        $data = $response->json('data');
        expect($data['total_claims'])->toBe(1);
    });
});

describe('Rejection Analysis Report', function () {
    it('returns rejection analysis data', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.view-reports');

        $provider = InsuranceProvider::factory()->create();
        $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
        $patient = Patient::factory()->create();
        $patientInsurance = PatientInsurance::factory()->create([
            'patient_id' => $patient->id,
            'insurance_plan_id' => $plan->id,
        ]);

        InsuranceClaim::factory()->create([
            'patient_id' => $patient->id,
            'patient_insurance_id' => $patientInsurance->id,
            'status' => 'rejected',
            'rejection_reason' => 'Invalid diagnosis code',
            'total_claim_amount' => 250.00,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('admin.insurance.reports.rejection-analysis', [
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->endOfMonth()->toDateString(),
            ]));

        $response->assertOk();
        $data = $response->json('data');
        expect($data['total_rejected'])->toBe(1);
        expect((float) $data['total_rejected_amount'])->toEqual(250.0);
    });

    it('groups rejections by reason', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.view-reports');

        $provider = InsuranceProvider::factory()->create();
        $plan = InsurancePlan::factory()->create(['insurance_provider_id' => $provider->id]);
        $patient = Patient::factory()->create();
        $patientInsurance = PatientInsurance::factory()->create([
            'patient_id' => $patient->id,
            'insurance_plan_id' => $plan->id,
        ]);

        // Create rejections with different reasons
        InsuranceClaim::factory()->count(2)->create([
            'patient_id' => $patient->id,
            'patient_insurance_id' => $patientInsurance->id,
            'status' => 'rejected',
            'rejection_reason' => 'Invalid diagnosis',
            'total_claim_amount' => 100.00,
        ]);

        InsuranceClaim::factory()->create([
            'patient_id' => $patient->id,
            'patient_insurance_id' => $patientInsurance->id,
            'status' => 'rejected',
            'rejection_reason' => 'Expired membership',
            'total_claim_amount' => 150.00,
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('admin.insurance.reports.rejection-analysis', [
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->endOfMonth()->toDateString(),
            ]));

        $response->assertOk();
        $data = $response->json('data');
        expect($data['total_rejected'])->toBe(3);
    });
});

describe('Tariff Coverage Report', function () {
    it('returns tariff coverage data', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.view-reports');

        // Create some items
        Drug::factory()->count(5)->create(['is_active' => true]);
        LabService::factory()->count(3)->create(['is_active' => true]);

        $response = $this->actingAs($user)
            ->getJson(route('admin.insurance.reports.tariff-coverage'));

        $response->assertOk();
        $data = $response->json('data');
        expect($data['coverage_by_type']['drugs']['total'])->toBe(5);
        expect($data['coverage_by_type']['lab_services']['total'])->toBe(3);
    });

    it('calculates coverage percentage correctly', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.view-reports');

        // Create 10 drugs, map 4
        $drugs = Drug::factory()->count(10)->create(['is_active' => true]);

        for ($i = 0; $i < 4; $i++) {
            $tariff = NhisTariff::factory()->medicine()->create();
            NhisItemMapping::create([
                'item_type' => 'drug',
                'item_id' => $drugs[$i]->id,
                'item_code' => $drugs[$i]->drug_code,
                'nhis_tariff_id' => $tariff->id,
            ]);
        }

        $response = $this->actingAs($user)
            ->getJson(route('admin.insurance.reports.tariff-coverage'));

        $response->assertOk();
        $data = $response->json('data');

        expect($data['coverage_by_type']['drugs']['total'])->toBe(10)
            ->and($data['coverage_by_type']['drugs']['mapped'])->toBe(4)
            ->and((float) $data['coverage_by_type']['drugs']['percentage'])->toEqual(40.0);
    });
});

describe('Excel Export', function () {
    it('exports claims summary to Excel', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.view-reports');

        $response = $this->actingAs($user)
            ->get(route('admin.insurance.reports.claims-summary.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    });

    it('exports outstanding claims to Excel', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.view-reports');

        $response = $this->actingAs($user)
            ->get(route('admin.insurance.reports.outstanding-claims.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    });

    it('exports rejection analysis to Excel', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.view-reports');

        $response = $this->actingAs($user)
            ->get(route('admin.insurance.reports.rejection-analysis.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    });

    it('exports tariff coverage to Excel', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('insurance.view-reports');

        $response = $this->actingAs($user)
            ->get(route('admin.insurance.reports.tariff-coverage.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    });
});
