<?php

use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\VitalSign;
use App\Models\Ward;
use Database\Seeders\PermissionSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->nurse = User::factory()->create();
    $this->nurse->assignRole('Nurse');

    $this->ward = Ward::factory()->create();
    $this->admission = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'status' => 'admitted',
    ]);

    $this->vitalSign = VitalSign::factory()->create([
        'patient_id' => $this->admission->patient_id,
        'patient_admission_id' => $this->admission->id,
        'recorded_by' => $this->nurse->id,
        'temperature' => 37.0,
        'blood_pressure_systolic' => 120,
        'blood_pressure_diastolic' => 80,
        'pulse_rate' => 72,
        'respiratory_rate' => 16,
        'recorded_at' => now()->subHours(2),
    ]);
});

it('allows admin to update vital signs values', function () {
    actingAs($this->admin)
        ->patch(route('admissions.vitals.update', [$this->admission, $this->vitalSign]), [
            'temperature' => 37.5,
            'blood_pressure_systolic' => 125,
            'blood_pressure_diastolic' => 85,
            'pulse_rate' => 75,
            'respiratory_rate' => 18,
        ])
        ->assertRedirect();

    $this->vitalSign->refresh();
    expect($this->vitalSign->temperature)->toBe('37.50');
    expect($this->vitalSign->pulse_rate)->toBe(75);
});

it('allows admin to update vital signs timestamp', function () {
    $newTimestamp = now()->subHours(4)->format('Y-m-d H:i:s');

    actingAs($this->admin)
        ->patch(route('admissions.vitals.update', [$this->admission, $this->vitalSign]), [
            'temperature' => 37.0,
            'blood_pressure_systolic' => 120,
            'blood_pressure_diastolic' => 80,
            'pulse_rate' => 72,
            'respiratory_rate' => 16,
            'recorded_at' => $newTimestamp,
        ])
        ->assertRedirect();

    $this->vitalSign->refresh();
    expect($this->vitalSign->recorded_at->format('Y-m-d H:i:s'))->toBe($newTimestamp);
});

it('prevents nurse without permission from updating timestamp', function () {
    $originalTimestamp = $this->vitalSign->recorded_at->format('Y-m-d H:i:s');
    $newTimestamp = now()->subHours(4)->format('Y-m-d H:i:s');

    actingAs($this->nurse)
        ->patch(route('admissions.vitals.update', [$this->admission, $this->vitalSign]), [
            'temperature' => 37.5,
            'blood_pressure_systolic' => 120,
            'blood_pressure_diastolic' => 80,
            'pulse_rate' => 72,
            'respiratory_rate' => 16,
            'recorded_at' => $newTimestamp,
        ])
        ->assertRedirect();

    $this->vitalSign->refresh();
    // Values should be updated
    expect($this->vitalSign->temperature)->toBe('37.50');
    // But timestamp should remain unchanged (nurse doesn't have permission)
    expect($this->vitalSign->recorded_at->format('Y-m-d H:i:s'))->toBe($originalTimestamp);
});

it('allows nurse with direct permission to update timestamp', function () {
    $this->nurse->givePermissionTo('vitals.edit-timestamp');
    $newTimestamp = now()->subHours(4)->format('Y-m-d H:i:s');

    actingAs($this->nurse)
        ->patch(route('admissions.vitals.update', [$this->admission, $this->vitalSign]), [
            'temperature' => 37.0,
            'blood_pressure_systolic' => 120,
            'blood_pressure_diastolic' => 80,
            'pulse_rate' => 72,
            'respiratory_rate' => 16,
            'recorded_at' => $newTimestamp,
        ])
        ->assertRedirect();

    $this->vitalSign->refresh();
    expect($this->vitalSign->recorded_at->format('Y-m-d H:i:s'))->toBe($newTimestamp);
});

it('returns 404 when vital sign does not belong to admission', function () {
    $otherAdmission = PatientAdmission::factory()->create([
        'ward_id' => $this->ward->id,
        'status' => 'admitted',
    ]);

    actingAs($this->admin)
        ->patch(route('admissions.vitals.update', [$otherAdmission, $this->vitalSign]), [
            'temperature' => 37.5,
        ])
        ->assertNotFound();
});

it('allows admin to create vital signs with custom timestamp', function () {
    $customTimestamp = now()->subHours(3)->format('Y-m-d H:i:s');

    actingAs($this->admin)
        ->post(route('admissions.vitals.store', $this->admission), [
            'temperature' => 36.8,
            'blood_pressure_systolic' => 118,
            'blood_pressure_diastolic' => 78,
            'pulse_rate' => 70,
            'respiratory_rate' => 15,
            'recorded_at' => $customTimestamp,
        ])
        ->assertRedirect();

    $newVital = VitalSign::where('patient_admission_id', $this->admission->id)
        ->where('temperature', 36.8)
        ->first();

    expect($newVital)->not->toBeNull();
    expect($newVital->recorded_at->format('Y-m-d H:i:s'))->toBe($customTimestamp);
});

it('creates vital signs with current time when nurse without permission provides timestamp', function () {
    $customTimestamp = now()->subHours(3)->format('Y-m-d H:i:s');

    actingAs($this->nurse)
        ->post(route('admissions.vitals.store', $this->admission), [
            'temperature' => 36.9,
            'blood_pressure_systolic' => 119,
            'blood_pressure_diastolic' => 79,
            'pulse_rate' => 71,
            'respiratory_rate' => 16,
            'recorded_at' => $customTimestamp,
        ])
        ->assertRedirect();

    $newVital = VitalSign::where('patient_admission_id', $this->admission->id)
        ->where('temperature', 36.9)
        ->first();

    expect($newVital)->not->toBeNull();
    // Timestamp should be close to now, not the custom timestamp
    expect($newVital->recorded_at->diffInMinutes(now()))->toBeLessThan(2);
});

it('allows nurse with permission to create vital signs with custom timestamp', function () {
    $this->nurse->givePermissionTo('vitals.edit-timestamp');
    $customTimestamp = now()->subHours(3)->format('Y-m-d H:i:s');

    actingAs($this->nurse)
        ->post(route('admissions.vitals.store', $this->admission), [
            'temperature' => 37.1,
            'blood_pressure_systolic' => 121,
            'blood_pressure_diastolic' => 81,
            'pulse_rate' => 73,
            'respiratory_rate' => 17,
            'recorded_at' => $customTimestamp,
        ])
        ->assertRedirect();

    $newVital = VitalSign::where('patient_admission_id', $this->admission->id)
        ->where('temperature', 37.1)
        ->first();

    expect($newVital)->not->toBeNull();
    expect($newVital->recorded_at->format('Y-m-d H:i:s'))->toBe($customTimestamp);
});
