<?php

use App\Models\Bed;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\VitalsSchedule;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('creates vitals schedule for admitted patient', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();
    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $response = postJson(route('wards.patients.vitals-schedule.store', [$ward, $admission]), [
        'interval_minutes' => 240,
    ]);

    $response->assertRedirect();

    expect(VitalsSchedule::where('patient_admission_id', $admission->id)->count())->toBe(1);

    $schedule = VitalsSchedule::where('patient_admission_id', $admission->id)->first();
    expect($schedule->interval_minutes)->toBe(240)
        ->and($schedule->is_active)->toBeTrue()
        ->and($schedule->created_by)->toBe($this->user->id);
});

it('updates existing vitals schedule interval', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();
    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'interval_minutes' => 120,
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $response = putJson(route('wards.patients.vitals-schedule.update', [$ward, $admission, $schedule]), [
        'interval_minutes' => 360,
    ]);

    $response->assertRedirect();

    $schedule->refresh();
    expect($schedule->interval_minutes)->toBe(360);
});

it('disables vitals schedule', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();
    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $response = deleteJson(route('wards.patients.vitals-schedule.destroy', [$ward, $admission, $schedule]));

    $response->assertRedirect();

    $schedule->refresh();
    expect($schedule->is_active)->toBeFalse();
});

it('validates interval is at least 15 minutes', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();
    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $response = postJson(route('wards.patients.vitals-schedule.store', [$ward, $admission]), [
        'interval_minutes' => 10,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['interval_minutes']);
});

it('validates interval does not exceed 1440 minutes', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();
    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $response = postJson(route('wards.patients.vitals-schedule.store', [$ward, $admission]), [
        'interval_minutes' => 1500,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['interval_minutes']);
});

it('validates interval is required', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();
    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $response = postJson(route('wards.patients.vitals-schedule.store', [$ward, $admission]), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['interval_minutes']);
});

it('validates interval is an integer', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();
    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $response = postJson(route('wards.patients.vitals-schedule.store', [$ward, $admission]), [
        'interval_minutes' => 'not-a-number',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['interval_minutes']);
});

it('requires authentication to create schedule', function () {
    auth()->logout();

    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();
    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $response = postJson(route('wards.patients.vitals-schedule.store', [$ward, $admission]), [
        'interval_minutes' => 240,
    ]);

    $response->assertUnauthorized();
});

it('requires authentication to update schedule', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();
    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'created_by' => $this->user->id,
    ]);

    auth()->logout();

    $response = putJson(route('wards.patients.vitals-schedule.update', [$ward, $admission, $schedule]), [
        'interval_minutes' => 360,
    ]);

    $response->assertUnauthorized();
});

it('requires authentication to delete schedule', function () {
    $ward = Ward::factory()->create();
    $bed = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $patient = Patient::factory()->create();
    $admission = PatientAdmission::factory()->create([
        'patient_id' => $patient->id,
        'bed_id' => $bed->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $schedule = VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission->id,
        'created_by' => $this->user->id,
    ]);

    auth()->logout();

    $response = deleteJson(route('wards.patients.vitals-schedule.destroy', [$ward, $admission, $schedule]));

    $response->assertUnauthorized();
});
