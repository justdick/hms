<?php

use App\Models\Patient;
use App\Models\PatientInsurance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions
    Permission::create(['name' => 'patients.view-all']);
    Permission::create(['name' => 'patients.create']);
    Permission::create(['name' => 'patients.update']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['patients.view-all', 'patients.create', 'patients.update']);
    $this->actingAs($this->user);
});

test('user can view patient list page', function () {
    Patient::factory()->count(3)->create(['status' => 'active']);

    $response = $this->get(route('patients.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Patients/Index')
        ->has('patients.data', 3)
        ->where('patients.total', 3)
    );
});

test('user can search patients', function () {
    Patient::factory()->create([
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
        ->has('patients.data', 1)
        ->where('patients.data.0.first_name', 'John')
    );
});

test('user can view patient profile', function () {
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

test('user can update patient information', function () {
    $patient = Patient::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    $response = $this->patch(route('patients.update', $patient), [
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'gender' => 'female',
        'date_of_birth' => '1990-01-01',
        'phone_number' => '+255123456789',
        'has_insurance' => false,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $patient->refresh();
    expect($patient->first_name)->toBe('Jane')
        ->and($patient->last_name)->toBe('Smith');
});

test('patient list includes active insurance information', function () {
    $patient = Patient::factory()->create(['status' => 'active']);
    PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'status' => 'active',
        'coverage_start_date' => now()->subMonth(),
        'coverage_end_date' => now()->addYear(),
    ]);

    $response = $this->get(route('patients.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Patients/Index')
        ->has('patients.data', 1)
        ->has('patients.data.0.active_insurance')
    );
});

test('patient profile includes insurance plans and checkin history', function () {
    $patient = Patient::factory()->create();
    PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'status' => 'active',
        'coverage_start_date' => now()->subMonth(),
        'coverage_end_date' => now()->addYear(),
    ]);

    $response = $this->get(route('patients.show', $patient));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Patients/Show')
        ->has('patient.active_insurance')
        ->has('patient.insurance_plans')
        ->has('patient.checkin_history')
    );
});

test('patient list includes recent incomplete checkin information', function () {
    $patient = Patient::factory()->create(['status' => 'active']);
    $department = \App\Models\Department::factory()->create();

    // Create an incomplete check-in
    $checkin = \App\Models\PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $department->id,
        'status' => 'checked_in',
        'checked_in_at' => now(),
    ]);

    $response = $this->get(route('patients.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Patients/Index')
        ->has('patients.data', 1)
        ->has('patients.data.0.recent_checkin')
        ->where('patients.data.0.recent_checkin.id', $checkin->id)
        ->where('patients.data.0.recent_checkin.status', 'checked_in')
    );
});

test('patient list does not include completed checkins as recent checkin', function () {
    $patient = Patient::factory()->create(['status' => 'active']);
    $department = \App\Models\Department::factory()->create();

    // Create a completed check-in
    \App\Models\PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $department->id,
        'status' => 'completed',
        'checked_in_at' => now(),
    ]);

    $response = $this->get(route('patients.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Patients/Index')
        ->has('patients.data', 1)
        ->where('patients.data.0.recent_checkin', null)
    );
});

test('patient list shows most recent incomplete checkin when multiple exist', function () {
    $patient = Patient::factory()->create(['status' => 'active']);
    $department = \App\Models\Department::factory()->create();

    // Create older incomplete check-in
    \App\Models\PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $department->id,
        'status' => 'vitals_taken',
        'checked_in_at' => now()->subHours(2),
    ]);

    // Create newer incomplete check-in
    $recentCheckin = \App\Models\PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $department->id,
        'status' => 'awaiting_consultation',
        'checked_in_at' => now(),
    ]);

    $response = $this->get(route('patients.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Patients/Index')
        ->has('patients.data', 1)
        ->has('patients.data.0.recent_checkin')
        ->where('patients.data.0.recent_checkin.id', $recentCheckin->id)
        ->where('patients.data.0.recent_checkin.status', 'awaiting_consultation')
    );
});

test('user can view patient edit page', function () {
    $patient = Patient::factory()->create();

    $response = $this->get(route('patients.edit', $patient));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Patients/Edit')
        ->has('patient')
        ->where('patient.id', $patient->id)
        ->has('insurance_plans')
    );
});

test('patient edit page includes active insurance data', function () {
    $patient = Patient::factory()->create();
    $insurance = PatientInsurance::factory()->create([
        'patient_id' => $patient->id,
        'status' => 'active',
        'coverage_start_date' => now()->subMonth(),
        'coverage_end_date' => now()->addYear(),
    ]);

    $response = $this->get(route('patients.edit', $patient));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Patients/Edit')
        ->has('patient.active_insurance')
        ->where('patient.active_insurance.membership_id', $insurance->membership_id)
    );
});

test('user can update patient with insurance information', function () {
    $patient = Patient::factory()->create();
    $insurancePlan = \App\Models\InsurancePlan::factory()->create();

    $response = $this->patch(route('patients.update', $patient), [
        'first_name' => 'Updated',
        'last_name' => 'Name',
        'gender' => 'male',
        'date_of_birth' => '1990-01-01',
        'has_insurance' => true,
        'insurance_plan_id' => $insurancePlan->id,
        'membership_id' => 'MEM123456',
        'coverage_start_date' => now()->format('Y-m-d'),
        'is_dependent' => false,
    ]);

    $response->assertRedirect(route('patients.show', $patient));

    $patient->refresh();
    expect($patient->first_name)->toBe('Updated')
        ->and($patient->activeInsurance)->not->toBeNull()
        ->and($patient->activeInsurance->membership_id)->toBe('MEM123456');
});

test('update redirects to patient profile page', function () {
    $patient = Patient::factory()->create();

    $response = $this->patch(route('patients.update', $patient), [
        'first_name' => 'Updated',
        'last_name' => 'Name',
        'gender' => 'male',
        'date_of_birth' => '1990-01-01',
        'has_insurance' => false,
    ]);

    $response->assertRedirect(route('patients.show', $patient));
});

// Authorization Tests

test('user without view permission cannot access patient list', function () {
    $unauthorizedUser = User::factory()->create();
    $this->actingAs($unauthorizedUser);

    $response = $this->get(route('patients.index'));

    $response->assertForbidden();
});

test('user without view permission cannot view patient profile', function () {
    $patient = Patient::factory()->create();
    $unauthorizedUser = User::factory()->create();
    $this->actingAs($unauthorizedUser);

    $response = $this->get(route('patients.show', $patient));

    $response->assertForbidden();
});

test('user without create permission cannot register patient', function () {
    $unauthorizedUser = User::factory()->create();
    $unauthorizedUser->givePermissionTo('patients.view-all');
    $this->actingAs($unauthorizedUser);

    $response = $this->post(route('checkin.patients.store'), [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'gender' => 'male',
        'date_of_birth' => '1990-01-01',
        'has_insurance' => false,
    ]);

    $response->assertForbidden();
});

test('user without update permission cannot update patient', function () {
    $patient = Patient::factory()->create();
    $unauthorizedUser = User::factory()->create();
    $unauthorizedUser->givePermissionTo('patients.view-all');
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

test('user without update permission cannot access patient edit page', function () {
    $patient = Patient::factory()->create();
    $unauthorizedUser = User::factory()->create();
    $unauthorizedUser->givePermissionTo('patients.view-all');
    $this->actingAs($unauthorizedUser);

    $response = $this->get(route('patients.edit', $patient));

    $response->assertForbidden();
});

test('user with patients.view permission can access patient list', function () {
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'patients.view']);
    $user->givePermissionTo('patients.view');
    $this->actingAs($user);

    Patient::factory()->count(2)->create();

    $response = $this->get(route('patients.index'));

    $response->assertSuccessful();
});

test('user with patients.view permission can view patient profile', function () {
    $user = User::factory()->create();
    Permission::firstOrCreate(['name' => 'patients.view']);
    $user->givePermissionTo('patients.view');
    $this->actingAs($user);

    $patient = Patient::factory()->create();

    $response = $this->get(route('patients.show', $patient));

    $response->assertSuccessful();
});

test('admin role can access all patient routes', function () {
    $admin = User::factory()->create();
    $adminRole = \Spatie\Permission\Models\Role::create(['name' => 'Admin']);
    $admin->assignRole($adminRole);
    $this->actingAs($admin);

    $patient = Patient::factory()->create();

    // Test all routes
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
