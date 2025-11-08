<?php

use App\Models\Bed;
use App\Models\Consultation;
use App\Models\Drug;
use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions
    Permission::create(['name' => 'administer medications']);
    Permission::create(['name' => 'manage prescriptions']);

    // Create roles
    $this->doctorRole = Role::create(['name' => 'doctor']);
    $this->nurseRole = Role::create(['name' => 'nurse']);

    // Assign permissions to roles
    $this->doctorRole->givePermissionTo(['administer medications', 'manage prescriptions']);
    $this->nurseRole->givePermissionTo(['administer medications', 'manage prescriptions']);

    // Create test data
    $this->ward = Ward::factory()->create();
    $this->bed = Bed::create([
        'ward_id' => $this->ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $this->patient = Patient::factory()->create();
    $this->drug = Drug::factory()->create();
    $this->consultation = Consultation::factory()->create();
    $this->admission = PatientAdmission::factory()->create([
        'patient_id' => $this->patient->id,
        'consultation_id' => $this->consultation->id,
        'bed_id' => $this->bed->id,
        'ward_id' => $this->ward->id,
        'status' => 'admitted',
    ]);
    $this->prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'BID',
        'duration' => '5 days',
        'quantity' => 0, // Avoid triggering charge creation in observer (observer checks for quantity > 0)
    ]);
});

// Test doctors can configure schedules
it('allows doctors to configure medication schedules', function () {
    $doctor = User::factory()->create();
    $doctor->assignRole('doctor');
    actingAs($doctor);

    $schedulePattern = [
        'day_1' => ['10:30', '18:00'],
        'subsequent' => ['06:00', '18:00'],
    ];

    $response = postJson(route('api.prescriptions.configure-schedule', $this->prescription), [
        'schedule_pattern' => $schedulePattern,
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Medication schedule configured successfully.',
        ]);

    $this->prescription->refresh();
    expect($this->prescription->schedule_pattern)->toBe($schedulePattern);
});

// Test nurses can configure schedules
it('allows nurses to configure medication schedules', function () {
    $nurse = User::factory()->create();
    $nurse->assignRole('nurse');
    actingAs($nurse);

    $schedulePattern = [
        'day_1' => ['10:30', '18:00'],
        'subsequent' => ['06:00', '18:00'],
    ];

    $response = postJson(route('api.prescriptions.configure-schedule', $this->prescription), [
        'schedule_pattern' => $schedulePattern,
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Medication schedule configured successfully.',
        ]);

    $this->prescription->refresh();
    expect($this->prescription->schedule_pattern)->toBe($schedulePattern);
});

// Test users without permission cannot configure
it('prevents users without manage prescriptions permission from configuring schedules', function () {
    $userWithoutPermission = User::factory()->create();
    actingAs($userWithoutPermission);

    $schedulePattern = [
        'day_1' => ['10:30', '18:00'],
        'subsequent' => ['06:00', '18:00'],
    ];

    $response = postJson(route('api.prescriptions.configure-schedule', $this->prescription), [
        'schedule_pattern' => $schedulePattern,
    ]);

    $response->assertForbidden();

    $this->prescription->refresh();
    expect($this->prescription->schedule_pattern)->toBeNull();
});

it('prevents users without manage prescriptions permission from reconfiguring schedules', function () {
    $userWithoutPermission = User::factory()->create();
    actingAs($userWithoutPermission);

    // Set up existing schedule
    $originalPattern = [
        'day_1' => ['08:00', '20:00'],
        'subsequent' => ['08:00', '20:00'],
    ];
    $this->prescription->update(['schedule_pattern' => $originalPattern]);

    $newPattern = [
        'day_1' => ['09:00', '21:00'],
        'subsequent' => ['09:00', '21:00'],
    ];

    $response = postJson(route('api.prescriptions.reconfigure-schedule', $this->prescription), [
        'schedule_pattern' => $newPattern,
    ]);

    $response->assertForbidden();

    $this->prescription->refresh();
    expect($this->prescription->schedule_pattern)->toBe($originalPattern);
});

it('prevents users without administer medications permission from adjusting schedule times', function () {
    $userWithoutPermission = User::factory()->create();
    actingAs($userWithoutPermission);

    $administration = MedicationAdministration::factory()->create([
        'prescription_id' => $this->prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->addHours(2),
        'status' => 'scheduled',
    ]);

    $response = patchJson(route('api.medication-administrations.adjust-time', $administration), [
        'scheduled_time' => now()->addHours(4)->toISOString(),
    ]);

    $response->assertForbidden();

    $administration->refresh();
    expect($administration->is_adjusted)->toBeFalse();
});

// Test doctors can discontinue prescriptions
it('allows doctors to discontinue prescriptions', function () {
    $doctor = User::factory()->create();
    $doctor->assignRole('doctor');
    actingAs($doctor);

    $response = postJson(route('api.prescriptions.discontinue', $this->prescription), [
        'reason' => 'Patient developed adverse reaction to medication',
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Prescription discontinued successfully.',
        ]);

    $this->prescription->refresh();
    expect($this->prescription->discontinued_at)->not->toBeNull()
        ->and($this->prescription->discontinued_by_id)->toBe($doctor->id)
        ->and($this->prescription->discontinuation_reason)->toBe('Patient developed adverse reaction to medication');
});

// Test nurses can discontinue prescriptions (if permitted)
it('allows nurses to discontinue prescriptions when they have permission', function () {
    $nurse = User::factory()->create();
    $nurse->assignRole('nurse');
    actingAs($nurse);

    $response = postJson(route('api.prescriptions.discontinue', $this->prescription), [
        'reason' => 'Patient developed adverse reaction to medication',
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Prescription discontinued successfully.',
        ]);

    $this->prescription->refresh();
    expect($this->prescription->discontinued_at)->not->toBeNull()
        ->and($this->prescription->discontinued_by_id)->toBe($nurse->id)
        ->and($this->prescription->discontinuation_reason)->toBe('Patient developed adverse reaction to medication');
});

it('prevents users without manage prescriptions permission from discontinuing prescriptions', function () {
    $userWithoutPermission = User::factory()->create();
    actingAs($userWithoutPermission);

    $response = postJson(route('api.prescriptions.discontinue', $this->prescription), [
        'reason' => 'Trying to discontinue without permission',
    ]);

    $response->assertForbidden();

    $this->prescription->refresh();
    expect($this->prescription->discontinued_at)->toBeNull();
});

// Test permission-based authorization for schedule adjustments
it('allows users with administer medications permission to adjust schedule times', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('administer medications');
    actingAs($user);

    $administration = MedicationAdministration::factory()->create([
        'prescription_id' => $this->prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->addHours(2),
        'status' => 'scheduled',
        'is_adjusted' => false,
    ]);

    $newTime = now()->addHours(4);

    $response = patchJson(route('api.medication-administrations.adjust-time', $administration), [
        'scheduled_time' => $newTime->toISOString(),
        'reason' => 'Patient requested later time',
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Medication schedule adjusted successfully.',
        ]);

    $administration->refresh();
    expect($administration->is_adjusted)->toBeTrue()
        ->and($administration->scheduled_time->format('Y-m-d H:i'))->toBe($newTime->format('Y-m-d H:i'));
});

it('allows users with manage prescriptions permission to reconfigure schedules', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('manage prescriptions');
    actingAs($user);

    // Set up existing schedule
    $originalPattern = [
        'day_1' => ['08:00', '20:00'],
        'subsequent' => ['08:00', '20:00'],
    ];
    $this->prescription->update(['schedule_pattern' => $originalPattern]);

    $newPattern = [
        'day_1' => ['09:00', '21:00'],
        'subsequent' => ['09:00', '21:00'],
    ];

    $response = postJson(route('api.prescriptions.reconfigure-schedule', $this->prescription), [
        'schedule_pattern' => $newPattern,
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Medication schedule reconfigured successfully.',
        ]);

    $this->prescription->refresh();
    expect($this->prescription->schedule_pattern)->toBe($newPattern);
});

// Test policy prevents configuring discontinued prescriptions
it('prevents configuring schedules for discontinued prescriptions', function () {
    $doctor = User::factory()->create();
    $doctor->assignRole('doctor');
    actingAs($doctor);

    // Discontinue the prescription first
    $this->prescription->update([
        'discontinued_at' => now(),
        'discontinued_by_id' => $doctor->id,
        'discontinuation_reason' => 'Test discontinuation',
    ]);

    $schedulePattern = [
        'day_1' => ['10:30', '18:00'],
        'subsequent' => ['06:00', '18:00'],
    ];

    $response = postJson(route('api.prescriptions.configure-schedule', $this->prescription), [
        'schedule_pattern' => $schedulePattern,
    ]);

    $response->assertForbidden();
});

// Test policy prevents reconfiguring discontinued prescriptions
it('prevents reconfiguring schedules for discontinued prescriptions', function () {
    $doctor = User::factory()->create();
    $doctor->assignRole('doctor');
    actingAs($doctor);

    // Set up existing schedule
    $originalPattern = [
        'day_1' => ['08:00', '20:00'],
        'subsequent' => ['08:00', '20:00'],
    ];
    $this->prescription->update(['schedule_pattern' => $originalPattern]);

    // Discontinue the prescription
    $this->prescription->update([
        'discontinued_at' => now(),
        'discontinued_by_id' => $doctor->id,
        'discontinuation_reason' => 'Test discontinuation',
    ]);

    $newPattern = [
        'day_1' => ['09:00', '21:00'],
        'subsequent' => ['09:00', '21:00'],
    ];

    $response = postJson(route('api.prescriptions.reconfigure-schedule', $this->prescription), [
        'schedule_pattern' => $newPattern,
    ]);

    $response->assertForbidden();
});

// Test policy prevents configuring PRN prescriptions
it('prevents configuring schedules for PRN prescriptions', function () {
    $doctor = User::factory()->create();
    $doctor->assignRole('doctor');
    actingAs($doctor);

    $prnPrescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'PRN',
        'duration' => '5 days',
    ]);

    $schedulePattern = [
        'day_1' => ['10:30', '18:00'],
        'subsequent' => ['06:00', '18:00'],
    ];

    $response = postJson(route('api.prescriptions.configure-schedule', $prnPrescription), [
        'schedule_pattern' => $schedulePattern,
    ]);

    $response->assertForbidden();
});

// Test policy prevents adjusting already given medications
it('prevents adjusting schedule times for already given medications', function () {
    $nurse = User::factory()->create();
    $nurse->assignRole('nurse');
    actingAs($nurse);

    $administration = MedicationAdministration::factory()->create([
        'prescription_id' => $this->prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->subHours(2),
        'status' => 'given',
        'administered_at' => now()->subHours(1),
        'administered_by_id' => $nurse->id,
    ]);

    $response = patchJson(route('api.medication-administrations.adjust-time', $administration), [
        'scheduled_time' => now()->addHours(2)->toISOString(),
    ]);

    $response->assertForbidden();
});

// Test policy prevents discontinuing already discontinued prescriptions
it('prevents discontinuing already discontinued prescriptions', function () {
    $doctor = User::factory()->create();
    $doctor->assignRole('doctor');
    actingAs($doctor);

    // Discontinue the prescription first
    $this->prescription->update([
        'discontinued_at' => now(),
        'discontinued_by_id' => $doctor->id,
        'discontinuation_reason' => 'Already discontinued',
    ]);

    $response = postJson(route('api.prescriptions.discontinue', $this->prescription), [
        'reason' => 'Trying to discontinue again',
    ]);

    $response->assertForbidden();
});
