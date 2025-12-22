<?php

use App\Models\Department;
use App\Models\InsurancePlan;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\PatientInsurance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions (use firstOrCreate to avoid duplicates)
    Permission::firstOrCreate(['name' => 'patients.view-all']);
    Permission::firstOrCreate(['name' => 'patients.view']);
    Permission::firstOrCreate(['name' => 'patients.create']);
    Permission::firstOrCreate(['name' => 'patients.update']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['patients.view-all', 'patients.create', 'patients.update']);
    $this->actingAs($this->user);
});

describe('Patient List Viewing', function () {
    it('displays patient list page with paginated patients', function () {
        Patient::factory()->count(12)->create(['status' => 'active']);

        $response = $this->get(route('patients.index'));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('Patients/Index')
            ->has('patients.data', 5) // Server-side pagination with 5 per page default
            ->where('patients.total', 12)
            ->where('patients.per_page', 5)
            ->where('patients.current_page', 1)
            ->where('patients.last_page', 3)
            ->has('departments')
            ->has('insurancePlans')
            ->has('filters')
        );
    });

    it('navigates to second page of patients', function () {
        Patient::factory()->count(12)->create(['status' => 'active']);

        $response = $this->get(route('patients.index', ['page' => 2]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('Patients/Index')
            ->has('patients.data', 5) // 5 patients on page 2
            ->where('patients.current_page', 2)
        );
    });

    it('allows changing per page count', function () {
        Patient::factory()->count(30)->create(['status' => 'active']);

        $response = $this->get(route('patients.index', ['per_page' => 10]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('Patients/Index')
            ->has('patients.data', 10)
            ->where('patients.per_page', 10)
            ->where('patients.last_page', 3)
        );
    });

    it('searches patients by first name', function () {
        $john = Patient::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'status' => 'active',
        ]);

        Patient::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'status' => 'active',
        ]);

        $response = $this->get(route('patients.index', ['search' => 'John']));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('Patients/Index')
            ->where('patients.data.0.first_name', 'John')
        );
    });

    it('searches patients by last name', function () {
        Patient::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'status' => 'active',
        ]);

        $jane = Patient::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'status' => 'active',
        ]);

        $response = $this->get(route('patients.index', ['search' => 'Smith']));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->where('patients.data.0.last_name', 'Smith')
        );
    });

    it('searches patients by patient number', function () {
        $uniqueNumber = 'P-TEST-'.uniqid();
        $patient = Patient::factory()->create([
            'patient_number' => $uniqueNumber,
            'status' => 'active',
        ]);

        Patient::factory()->create([
            'patient_number' => 'P-2024-002',
            'status' => 'active',
        ]);

        $response = $this->get(route('patients.index', ['search' => $uniqueNumber]));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->where('patients.data.0.patient_number', $uniqueNumber)
        );
    });

    it('searches patients by phone number', function () {
        Patient::factory()->create([
            'first_name' => 'John',
            'phone_number' => '+255123456789',
            'status' => 'active',
        ]);

        Patient::factory()->create([
            'first_name' => 'Jane',
            'phone_number' => '+255987654321',
            'status' => 'active',
        ]);

        $response = $this->get(route('patients.index', ['search' => '+255123456789']));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->where('patients.data.0.phone_number', '+255123456789')
        );
    });

    it('searches patients by insurance membership ID', function () {
        $patientWithInsurance = Patient::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Insured',
            'status' => 'active',
        ]);

        $insurancePlan = InsurancePlan::factory()->create();
        PatientInsurance::factory()->create([
            'patient_id' => $patientWithInsurance->id,
            'insurance_plan_id' => $insurancePlan->id,
            'membership_id' => 'NHIS-2025-12345',
            'status' => 'active',
            'coverage_start_date' => now()->subMonth(),
            'coverage_end_date' => now()->addYear(),
        ]);

        Patient::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'NoInsurance',
            'status' => 'active',
        ]);

        $response = $this->get(route('patients.index', ['search' => 'NHIS-2025-12345']));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('patients.data', 1)
            ->where('patients.data.0.first_name', 'John')
            ->where('patients.data.0.last_name', 'Insured')
        );
    });

    it('includes active insurance information in patient list', function () {
        $patient = Patient::factory()->create(['status' => 'active']);
        $insurancePlan = \App\Models\InsurancePlan::factory()->create();
        PatientInsurance::factory()->create([
            'patient_id' => $patient->id,
            'insurance_plan_id' => $insurancePlan->id,
            'status' => 'active',
            'coverage_start_date' => now()->subMonth(),
            'coverage_end_date' => now()->addYear(),
        ]);

        $response = $this->get(route('patients.index'));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('patients.data.0.active_insurance')
            ->has('patients.data.0.active_insurance.insurance_plan')
        );
    });

    it('includes recent incomplete check-in information', function () {
        $patient = Patient::factory()->create(['status' => 'active']);
        $department = Department::factory()->create();

        $checkin = PatientCheckin::factory()->create([
            'patient_id' => $patient->id,
            'department_id' => $department->id,
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);

        $response = $this->get(route('patients.index'));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('patients.data.0.recent_checkin')
            ->where('patients.data.0.recent_checkin.id', $checkin->id)
            ->where('patients.data.0.recent_checkin.status', 'checked_in')
        );
    });
});

describe('Patient Profile Viewing', function () {
    it('displays patient profile page', function () {
        $patient = Patient::factory()->create();

        $response = $this->get(route('patients.show', $patient));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('Patients/Show')
            ->has('patient')
            ->where('patient.id', $patient->id)
            ->where('patient.full_name', $patient->full_name)
        );
    });

    it('includes patient demographics on profile', function () {
        $patient = Patient::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'date_of_birth' => '1990-01-15',
            'phone_number' => '+255123456789',
        ]);

        $response = $this->get(route('patients.show', $patient));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->where('patient.first_name', 'John')
            ->where('patient.last_name', 'Doe')
            ->where('patient.gender', 'male')
            ->where('patient.phone_number', '+255123456789')
        );
    });

    it('includes insurance information on profile', function () {
        $patient = Patient::factory()->create();
        $insurance = PatientInsurance::factory()->create([
            'patient_id' => $patient->id,
            'status' => 'active',
            'coverage_start_date' => now()->subMonth(),
            'coverage_end_date' => now()->addYear(),
        ]);

        $response = $this->get(route('patients.show', $patient));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('patient.active_insurance')
            ->has('patient.insurance_plans')
        );
    });

    it('includes check-in history on profile', function () {
        $patient = Patient::factory()->create();
        $department = Department::factory()->create();

        PatientCheckin::factory()->count(3)->create([
            'patient_id' => $patient->id,
            'department_id' => $department->id,
            'status' => 'completed',
        ]);

        $response = $this->get(route('patients.show', $patient));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('patient.checkin_history', 3)
        );
    });
});

describe('Patient Registration', function () {
    it('registers patient without insurance', function () {
        $response = $this->post(route('checkin.patients.store'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'date_of_birth' => '1990-01-15',
            'phone_number' => '+255123456789',
            'has_insurance' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('patient');

        $this->assertDatabaseHas('patients', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'phone_number' => '+255123456789',
        ]);
    });

    it('registers patient with insurance', function () {
        $insurancePlan = InsurancePlan::factory()->create();

        $response = $this->post(route('checkin.patients.store'), [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'gender' => 'female',
            'date_of_birth' => '1985-05-20',
            'phone_number' => '+255987654321',
            'has_insurance' => true,
            'insurance_plan_id' => $insurancePlan->id,
            'membership_id' => 'MEM123456',
            'coverage_start_date' => now()->format('Y-m-d'),
            'is_dependent' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('patient');

        $patient = Patient::where('first_name', 'Jane')->first();

        $this->assertDatabaseHas('patients', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);

        $this->assertDatabaseHas('patient_insurance', [
            'patient_id' => $patient->id,
            'insurance_plan_id' => $insurancePlan->id,
            'membership_id' => 'MEM123456',
            'status' => 'active',
        ]);
    });

    it('registers patient with dependent insurance', function () {
        $insurancePlan = InsurancePlan::factory()->create();

        $response = $this->post(route('checkin.patients.store'), [
            'first_name' => 'Child',
            'last_name' => 'Dependent',
            'gender' => 'male',
            'date_of_birth' => '2015-03-10',
            'has_insurance' => true,
            'insurance_plan_id' => $insurancePlan->id,
            'membership_id' => 'MEM789012',
            'coverage_start_date' => now()->format('Y-m-d'),
            'is_dependent' => true,
            'principal_member_name' => 'Parent Name',
            'relationship_to_principal' => 'child',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('patient');

        $patient = Patient::where('first_name', 'Child')->first();

        $this->assertDatabaseHas('patient_insurance', [
            'patient_id' => $patient->id,
            'is_dependent' => true,
            'principal_member_name' => 'Parent Name',
            'relationship_to_principal' => 'child',
        ]);
    });
});

describe('Patient Information Update', function () {
    it('updates patient demographics', function () {
        $patient = Patient::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->patch(route('patients.update', $patient), [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'gender' => 'female',
            'date_of_birth' => '1990-01-01',
            'phone_number' => '+255111222333',
            'has_insurance' => false,
        ]);

        $response->assertRedirect(route('patients.show', $patient));
        $response->assertSessionHas('success');

        $patient->refresh();
        expect($patient->first_name)->toBe('Jane')
            ->and($patient->last_name)->toBe('Smith')
            ->and($patient->phone_number)->toBe('+255111222333');
    });

    it('updates patient with new insurance information', function () {
        $patient = Patient::factory()->create();
        $insurancePlan = InsurancePlan::factory()->create();

        $response = $this->patch(route('patients.update', $patient), [
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'gender' => $patient->gender,
            'date_of_birth' => $patient->date_of_birth->format('Y-m-d'),
            'has_insurance' => true,
            'insurance_plan_id' => $insurancePlan->id,
            'membership_id' => 'NEW123456',
            'coverage_start_date' => now()->format('Y-m-d'),
            'is_dependent' => false,
        ]);

        $response->assertRedirect(route('patients.show', $patient));

        $patient->refresh();
        expect($patient->activeInsurance)->not->toBeNull()
            ->and($patient->activeInsurance->membership_id)->toBe('NEW123456');
    });

    it('updates existing insurance information', function () {
        $patient = Patient::factory()->create();
        $insurancePlan = InsurancePlan::factory()->create();
        $insurance = PatientInsurance::factory()->create([
            'patient_id' => $patient->id,
            'insurance_plan_id' => $insurancePlan->id,
            'membership_id' => 'OLD123456',
            'status' => 'active',
        ]);

        $newInsurancePlan = InsurancePlan::factory()->create();

        $response = $this->patch(route('patients.update', $patient), [
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'gender' => $patient->gender,
            'date_of_birth' => $patient->date_of_birth->format('Y-m-d'),
            'has_insurance' => true,
            'insurance_plan_id' => $newInsurancePlan->id,
            'membership_id' => 'UPDATED123456',
            'coverage_start_date' => now()->format('Y-m-d'),
            'is_dependent' => false,
        ]);

        $response->assertRedirect(route('patients.show', $patient));

        $patient->refresh();
        expect($patient->activeInsurance->membership_id)->toBe('UPDATED123456')
            ->and($patient->activeInsurance->insurance_plan_id)->toBe($newInsurancePlan->id);
    });

    it('does not modify insurance when has_insurance is false', function () {
        $patient = Patient::factory()->create();
        $insurance = PatientInsurance::factory()->create([
            'patient_id' => $patient->id,
            'status' => 'active',
        ]);

        $response = $this->patch(route('patients.update', $patient), [
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'gender' => $patient->gender,
            'date_of_birth' => $patient->date_of_birth->format('Y-m-d'),
            'has_insurance' => false,
        ]);

        $response->assertRedirect(route('patients.show', $patient));

        $insurance->refresh();
        // Insurance remains unchanged when has_insurance is false
        expect($insurance->status)->toBe('active');
    });
});

describe('Validation Errors', function () {
    it('requires first name for registration', function () {
        $response = $this->post(route('checkin.patients.store'), [
            'last_name' => 'Doe',
            'gender' => 'male',
            'date_of_birth' => '1990-01-15',
            'has_insurance' => false,
        ]);

        $response->assertSessionHasErrors('first_name');
    });

    it('requires last name for registration', function () {
        $response = $this->post(route('checkin.patients.store'), [
            'first_name' => 'John',
            'gender' => 'male',
            'date_of_birth' => '1990-01-15',
            'has_insurance' => false,
        ]);

        $response->assertSessionHasErrors('last_name');
    });

    it('requires gender for registration', function () {
        $response = $this->post(route('checkin.patients.store'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-15',
            'has_insurance' => false,
        ]);

        $response->assertSessionHasErrors('gender');
    });

    it('requires date of birth for registration', function () {
        $response = $this->post(route('checkin.patients.store'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'has_insurance' => false,
        ]);

        $response->assertSessionHasErrors('date_of_birth');
    });

    it('validates date of birth is in the past', function () {
        $response = $this->post(route('checkin.patients.store'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'date_of_birth' => now()->addDay()->format('Y-m-d'),
            'has_insurance' => false,
        ]);

        $response->assertSessionHasErrors('date_of_birth');
    });

    it('requires insurance fields when has_insurance is true', function () {
        $response = $this->post(route('checkin.patients.store'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'date_of_birth' => '1990-01-15',
            'has_insurance' => true,
        ]);

        $response->assertSessionHasErrors(['insurance_plan_id', 'membership_id', 'coverage_start_date']);
    });

    it('requires dependent fields when is_dependent is true', function () {
        $insurancePlan = InsurancePlan::factory()->create();

        $response = $this->post(route('checkin.patients.store'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'date_of_birth' => '1990-01-15',
            'has_insurance' => true,
            'insurance_plan_id' => $insurancePlan->id,
            'membership_id' => 'MEM123456',
            'coverage_start_date' => now()->format('Y-m-d'),
            'is_dependent' => true,
        ]);

        $response->assertSessionHasErrors(['principal_member_name', 'relationship_to_principal']);
    });
});

describe('Authorization Checks', function () {
    it('prevents unauthorized user from viewing patient list', function () {
        $unauthorizedUser = User::factory()->create();
        $this->actingAs($unauthorizedUser);

        $response = $this->get(route('patients.index'));

        $response->assertForbidden();
    });

    it('prevents unauthorized user from viewing patient profile', function () {
        $patient = Patient::factory()->create();
        $unauthorizedUser = User::factory()->create();
        $this->actingAs($unauthorizedUser);

        $response = $this->get(route('patients.show', $patient));

        $response->assertForbidden();
    });

    it('prevents unauthorized user from registering patient', function () {
        $unauthorizedUser = User::factory()->create();
        $this->actingAs($unauthorizedUser);

        $response = $this->post(route('checkin.patients.store'), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'date_of_birth' => '1990-01-15',
            'has_insurance' => false,
        ]);

        $response->assertForbidden();
    });

    it('prevents unauthorized user from updating patient', function () {
        $patient = Patient::factory()->create();
        $unauthorizedUser = User::factory()->create();
        $this->actingAs($unauthorizedUser);

        $response = $this->patch(route('patients.update', $patient), [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'has_insurance' => false,
        ]);

        $response->assertForbidden();
    });

    it('allows user with patients.view permission to view patient list', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('patients.view');
        $this->actingAs($user);

        Patient::factory()->count(2)->create();

        $response = $this->get(route('patients.index'));

        $response->assertSuccessful();
    });

    it('allows admin role to access all patient routes', function () {
        $admin = User::factory()->create();
        $adminRole = Role::create(['name' => 'Admin']);
        $admin->assignRole($adminRole);
        $this->actingAs($admin);

        $patient = Patient::factory()->create();

        $this->get(route('patients.index'))->assertSuccessful();
        $this->get(route('patients.show', $patient))->assertSuccessful();
        $this->get(route('patients.edit', $patient))->assertSuccessful();

        $response = $this->patch(route('patients.update', $patient), [
            'first_name' => 'Admin',
            'last_name' => 'Updated',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'has_insurance' => false,
        ]);

        $response->assertRedirect();
    });
});
