<?php

use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\Ward;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Create the permission if it doesn't exist
    Permission::firstOrCreate(['name' => 'admissions.discharge', 'guard_name' => 'web']);

    // Create a user with discharge permission
    $this->doctor = User::factory()->create();
    $this->doctor->givePermissionTo('admissions.discharge');

    // Create a user without discharge permission
    $this->nurse = User::factory()->create();

    // Create ward and admission
    $this->ward = Ward::factory()->create();
    $this->patient = Patient::factory()->create();
    $this->admission = PatientAdmission::factory()->create([
        'patient_id' => $this->patient->id,
        'ward_id' => $this->ward->id,
        'status' => 'admitted',
    ]);
});

it('allows user with permission to discharge patient', function () {
    $response = $this->actingAs($this->doctor)
        ->post("/wards/{$this->ward->id}/patients/{$this->admission->id}/discharge", [
            'discharge_notes' => 'Patient recovered well.',
        ]);

    $response->assertRedirect("/wards/{$this->ward->id}");

    $this->admission->refresh();
    expect($this->admission->status)->toBe('discharged')
        ->and($this->admission->discharged_at)->not->toBeNull()
        ->and($this->admission->discharged_by_id)->toBe($this->doctor->id)
        ->and($this->admission->discharge_notes)->toBe('Patient recovered well.');
});

it('denies user without permission to discharge patient', function () {
    $response = $this->actingAs($this->nurse)
        ->post("/wards/{$this->ward->id}/patients/{$this->admission->id}/discharge", [
            'discharge_notes' => 'Trying to discharge.',
        ]);

    $response->assertForbidden();

    $this->admission->refresh();
    expect($this->admission->status)->toBe('admitted');
});

it('prevents discharging already discharged patient', function () {
    // First discharge
    $this->admission->update([
        'status' => 'discharged',
        'discharged_at' => now(),
    ]);

    $response = $this->actingAs($this->doctor)
        ->post("/wards/{$this->ward->id}/patients/{$this->admission->id}/discharge");

    $response->assertSessionHasErrors('admission');
});

it('allows discharge without notes', function () {
    $response = $this->actingAs($this->doctor)
        ->post("/wards/{$this->ward->id}/patients/{$this->admission->id}/discharge");

    $response->assertRedirect("/wards/{$this->ward->id}");

    $this->admission->refresh();
    expect($this->admission->status)->toBe('discharged')
        ->and($this->admission->discharge_notes)->toBeNull();
});
