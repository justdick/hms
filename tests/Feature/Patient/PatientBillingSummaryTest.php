<?php

use App\Models\Charge;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions
    Permission::create(['name' => 'patients.view-all']);
    Permission::create(['name' => 'patients.view']);
    Permission::create(['name' => 'billing.create']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['patients.view-all', 'patients.view', 'billing.create']);
    $this->actingAs($this->user);
});

describe('Patient Billing Summary', function () {
    it('displays billing summary on patient profile when user has permission', function () {
        $patient = Patient::factory()->create();
        $checkin = PatientCheckin::factory()->create(['patient_id' => $patient->id]);

        Charge::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'amount' => 150.00,
            'patient_copay_amount' => 150.00,
            'insurance_covered_amount' => 0,
            'status' => 'pending',
        ]);

        $response = $this->get(route('patients.show', $patient));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('Patients/Show')
            ->has('billing_summary')
            ->where('billing_summary.total_outstanding', 150)
            ->where('billing_summary.patient_owes', 150)
            ->where('can_process_payment', true)
        );
    });

    it('shows no outstanding balance when all charges are paid', function () {
        $patient = Patient::factory()->create();
        $checkin = PatientCheckin::factory()->create(['patient_id' => $patient->id]);

        Charge::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'amount' => 100.00,
            'status' => 'paid',
            'paid_at' => now(),
            'paid_amount' => 100.00,
            'metadata' => ['payment_method' => 'cash'],
        ]);

        $response = $this->get(route('patients.show', $patient));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('Patients/Show')
            ->has('billing_summary')
            ->where('billing_summary.total_outstanding', 0)
            ->has('billing_summary.recent_payments', 1)
        );
    });

    it('displays recent payment history', function () {
        $patient = Patient::factory()->create();
        $checkin = PatientCheckin::factory()->create(['patient_id' => $patient->id]);

        // Create 3 paid charges
        for ($i = 0; $i < 3; $i++) {
            Charge::factory()->create([
                'patient_checkin_id' => $checkin->id,
                'amount' => 50.00,
                'status' => 'paid',
                'paid_at' => now()->subDays($i),
                'paid_amount' => 50.00,
                'description' => "Service $i",
                'metadata' => ['payment_method' => 'cash'],
            ]);
        }

        $response = $this->get(route('patients.show', $patient));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('Patients/Show')
            ->has('billing_summary')
            ->has('billing_summary.recent_payments', 3)
        );
    });

    it('shows insurance covered amount when patient has insurance', function () {
        $patient = Patient::factory()->create();
        $checkin = PatientCheckin::factory()->create(['patient_id' => $patient->id]);

        Charge::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'amount' => 200.00,
            'patient_copay_amount' => 40.00,
            'insurance_covered_amount' => 160.00,
            'status' => 'pending',
        ]);

        $response = $this->get(route('patients.show', $patient));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('Patients/Show')
            ->has('billing_summary')
            ->where('billing_summary.total_outstanding', 200)
            ->where('billing_summary.insurance_covered', 160)
            ->where('billing_summary.patient_owes', 40)
        );
    });

    it('does not show process payment button when user lacks permission', function () {
        // Create user without billing.create permission
        $userWithoutBillingPermission = User::factory()->create();
        $userWithoutBillingPermission->givePermissionTo(['patients.view-all', 'patients.view']);
        $this->actingAs($userWithoutBillingPermission);

        $patient = Patient::factory()->create();
        $checkin = PatientCheckin::factory()->create(['patient_id' => $patient->id]);

        Charge::factory()->create([
            'patient_checkin_id' => $checkin->id,
            'amount' => 100.00,
            'status' => 'pending',
        ]);

        $response = $this->get(route('patients.show', $patient));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('Patients/Show')
            ->has('billing_summary')
            ->where('can_process_payment', false)
        );
    });
});
