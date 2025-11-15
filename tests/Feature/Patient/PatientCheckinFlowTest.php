<?php

use App\Models\Department;
use App\Models\InsurancePlan;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions
    Permission::create(['name' => 'patients.create']);
    Permission::create(['name' => 'checkins.create']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['patients.create', 'checkins.create']);
    $this->actingAs($this->user);

    $this->department = Department::factory()->create();
});

describe('Check-in Prompt After Registration', function () {
    it('returns patient data in session after successful registration', function () {
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

        $patientData = session('patient');
        expect($patientData)->toHaveKeys(['id', 'patient_number', 'full_name', 'age', 'gender', 'phone_number']);
    });

    it('includes patient information in session for check-in prompt', function () {
        $response = $this->post(route('checkin.patients.store'), [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'gender' => 'female',
            'date_of_birth' => '1985-05-20',
            'has_insurance' => false,
        ]);

        $response->assertRedirect();
        $patientData = session('patient');

        expect($patientData['full_name'])->toBe('Jane Smith')
            ->and($patientData['gender'])->toBe('female');
    });
});

describe('Immediate Check-in After Registration', function () {
    it('can check in patient immediately after registration', function () {
        $patient = Patient::factory()->create();

        $response = $this->post(route('checkin.checkins.store'), [
            'patient_id' => $patient->id,
            'department_id' => $this->department->id,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('patient_checkins', [
            'patient_id' => $patient->id,
            'department_id' => $this->department->id,
            'status' => 'checked_in',
        ]);
    });

    it('creates check-in with correct initial status', function () {
        $patient = Patient::factory()->create();

        $response = $this->post(route('checkin.checkins.store'), [
            'patient_id' => $patient->id,
            'department_id' => $this->department->id,
        ]);

        $checkin = PatientCheckin::where('patient_id', $patient->id)->first();

        expect($checkin)->not->toBeNull()
            ->and($checkin->status)->toBe('checked_in')
            ->and($checkin->checked_in_at)->not->toBeNull();
    });

    it('associates check-in with correct department', function () {
        $patient = Patient::factory()->create();
        $specificDepartment = Department::factory()->create(['name' => 'Cardiology']);

        $response = $this->post(route('checkin.checkins.store'), [
            'patient_id' => $patient->id,
            'department_id' => $specificDepartment->id,
        ]);

        $checkin = PatientCheckin::where('patient_id', $patient->id)->first();

        expect($checkin->department_id)->toBe($specificDepartment->id)
            ->and($checkin->department->name)->toBe('Cardiology');
    });
});

describe('Skipping Immediate Check-in', function () {
    it('allows registration without immediate check-in', function () {
        $response = $this->post(route('checkin.patients.store'), [
            'first_name' => 'Skip',
            'last_name' => 'Checkin',
            'gender' => 'male',
            'date_of_birth' => '1990-01-15',
            'has_insurance' => false,
        ]);

        $response->assertRedirect();
        $patient = Patient::where('first_name', 'Skip')->first();

        // Patient is created but no check-in exists
        expect($patient)->not->toBeNull();
        $this->assertDatabaseMissing('patient_checkins', [
            'patient_id' => $patient->id,
        ]);
    });

    it('patient can be checked in later after skipping immediate check-in', function () {
        // Register patient
        $this->post(route('checkin.patients.store'), [
            'first_name' => 'Later',
            'last_name' => 'Checkin',
            'gender' => 'female',
            'date_of_birth' => '1992-03-10',
            'has_insurance' => false,
        ]);

        $patient = Patient::where('first_name', 'Later')->first();

        // Check in later
        $response = $this->post(route('checkin.checkins.store'), [
            'patient_id' => $patient->id,
            'department_id' => $this->department->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('patient_checkins', [
            'patient_id' => $patient->id,
            'department_id' => $this->department->id,
        ]);
    });
});

describe('Quick Check-in from Patient List', function () {
    it('can check in patient from patient list', function () {
        $patient = Patient::factory()->create();

        $response = $this->post(route('checkin.checkins.store'), [
            'patient_id' => $patient->id,
            'department_id' => $this->department->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('patient_checkins', [
            'patient_id' => $patient->id,
            'department_id' => $this->department->id,
            'status' => 'checked_in',
        ]);
    });

    it('quick check-in creates proper check-in record', function () {
        $patient = Patient::factory()->create([
            'first_name' => 'Quick',
            'last_name' => 'Checkin',
        ]);

        $response = $this->post(route('checkin.checkins.store'), [
            'patient_id' => $patient->id,
            'department_id' => $this->department->id,
        ]);

        $checkin = PatientCheckin::where('patient_id', $patient->id)->first();

        expect($checkin)->not->toBeNull()
            ->and($checkin->patient->full_name)->toBe('Quick Checkin')
            ->and($checkin->department_id)->toBe($this->department->id)
            ->and($checkin->status)->toBe('checked_in');
    });

    it('can check in patient with insurance from patient list', function () {
        $patient = Patient::factory()->create();
        $insurancePlan = InsurancePlan::factory()->create();
        $patient->insurancePlans()->create([
            'insurance_plan_id' => $insurancePlan->id,
            'membership_id' => 'MEM123',
            'coverage_start_date' => now(),
            'status' => 'active',
        ]);

        $response = $this->post(route('checkin.checkins.store'), [
            'patient_id' => $patient->id,
            'department_id' => $this->department->id,
        ]);

        $response->assertRedirect();
        $checkin = PatientCheckin::where('patient_id', $patient->id)->first();

        expect($checkin)->not->toBeNull()
            ->and($patient->activeInsurance)->not->toBeNull();
    });
});

describe('Check-in Validation', function () {
    it('requires patient_id for check-in', function () {
        $response = $this->post(route('checkin.checkins.store'), [
            'department_id' => $this->department->id,
        ]);

        $response->assertSessionHasErrors('patient_id');
    });

    it('requires department_id for check-in', function () {
        $patient = Patient::factory()->create();

        $response = $this->post(route('checkin.checkins.store'), [
            'patient_id' => $patient->id,
        ]);

        $response->assertSessionHasErrors('department_id');
    });

    it('validates patient exists', function () {
        $response = $this->post(route('checkin.checkins.store'), [
            'patient_id' => 99999,
            'department_id' => $this->department->id,
        ]);

        $response->assertSessionHasErrors('patient_id');
    });

    it('validates department exists', function () {
        $patient = Patient::factory()->create();

        $response = $this->post(route('checkin.checkins.store'), [
            'patient_id' => $patient->id,
            'department_id' => 99999,
        ]);

        $response->assertSessionHasErrors('department_id');
    });
});

describe('Check-in Authorization', function () {
    it('prevents unauthorized user from checking in patient', function () {
        $unauthorizedUser = User::factory()->create();
        $this->actingAs($unauthorizedUser);

        $patient = Patient::factory()->create();

        $response = $this->post(route('checkin.checkins.store'), [
            'patient_id' => $patient->id,
            'department_id' => $this->department->id,
        ]);

        $response->assertForbidden();
    });

    it('allows authorized user to check in patient', function () {
        $patient = Patient::factory()->create();

        $response = $this->post(route('checkin.checkins.store'), [
            'patient_id' => $patient->id,
            'department_id' => $this->department->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('patient_checkins', [
            'patient_id' => $patient->id,
        ]);
    });
});
