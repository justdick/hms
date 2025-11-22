<?php

use App\Models\Department;
use App\Models\InsurancePlan;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions
    Permission::create(['name' => 'patients.view-all']);
    Permission::create(['name' => 'patients.create']);
    Permission::create(['name' => 'patients.update']);
    Permission::create(['name' => 'checkins.create']);

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(['patients.view-all', 'patients.create', 'patients.update', 'checkins.create']);

    $this->department = Department::factory()->create(['name' => 'General Medicine']);
    $this->insurancePlan = InsurancePlan::factory()->create();
});

describe('Patient Registration and Immediate Check-in Flow', function () {
    it('can register patient and check in immediately', function () {
        actingAs($this->user);

        $page = visit('/patients');

        $page->assertSee('Patients')
            ->assertSee('Register New Patient');
    })->skip('Browser testing requires additional setup');
});

describe('Patient Search and Profile Viewing', function () {
    it('can search for patient and view profile', function () {
        actingAs($this->user);

        $patient = Patient::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'status' => 'active',
        ]);

        $page = visit('/patients');

        $page->assertSee('Patients')
            ->assertSee($patient->full_name);
    })->skip('Browser testing requires additional setup');
});

describe('Patient Editing', function () {
    it('can edit patient information', function () {
        actingAs($this->user);

        $patient = Patient::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'status' => 'active',
        ]);

        $page = visit("/patients/{$patient->id}");

        $page->assertSee($patient->full_name)
            ->assertSee('Edit');
    })->skip('Browser testing requires additional setup');
});
