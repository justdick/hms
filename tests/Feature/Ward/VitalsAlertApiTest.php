<?php

use App\Models\Bed;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\VitalsAlert;
use App\Models\VitalsSchedule;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('returns active alerts for user', function () {
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

    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
        'status' => 'due',
        'due_at' => now()->subMinutes(5),
    ]);

    $response = getJson(route('vitals-alerts.active'));

    $response->assertSuccessful()
        ->assertJson([
            'alerts' => [
                [
                    'id' => $alert->id,
                    'patient_admission_id' => $admission->id,
                    'ward_id' => $ward->id,
                    'patient_name' => $patient->first_name.' '.$patient->last_name,
                    'bed_number' => '01',
                    'ward_name' => $ward->name,
                    'status' => 'due',
                ],
            ],
        ]);
});

it('does not return completed alerts', function () {
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

    VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
        'status' => 'completed',
        'due_at' => now()->subMinutes(5),
    ]);

    $response = getJson(route('vitals-alerts.active'));

    $response->assertSuccessful()
        ->assertJson(['alerts' => []]);
});

it('acknowledges alert', function () {
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

    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
        'status' => 'due',
        'acknowledged_at' => null,
        'acknowledged_by' => null,
    ]);

    $response = postJson(route('vitals-alerts.acknowledge', $alert));

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Alert acknowledged successfully.',
            'alert' => [
                'id' => $alert->id,
            ],
        ]);

    $alert->refresh();
    expect($alert->acknowledged_by)->toBe($this->user->id)
        ->and($alert->acknowledged_at)->not->toBeNull();
});

it('dismisses alert', function () {
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

    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
        'status' => 'due',
    ]);

    $response = postJson(route('vitals-alerts.dismiss', $alert));

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Alert dismissed successfully.',
        ]);

    $alert->refresh();
    expect($alert->status)->toBe('dismissed')
        ->and($alert->acknowledged_by)->toBe($this->user->id);
});

it('requires authentication to get active alerts', function () {
    auth()->logout();

    $response = getJson(route('vitals-alerts.active'));

    $response->assertUnauthorized();
});

it('requires authentication to acknowledge alert', function () {
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

    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
        'status' => 'due',
    ]);

    auth()->logout();

    $response = postJson(route('vitals-alerts.acknowledge', $alert));

    $response->assertUnauthorized();
});

it('requires authentication to dismiss alert', function () {
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

    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
        'status' => 'due',
    ]);

    auth()->logout();

    $response = postJson(route('vitals-alerts.dismiss', $alert));

    $response->assertUnauthorized();
});

it('includes time overdue for overdue alerts', function () {
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

    $alert = VitalsAlert::factory()->create([
        'vitals_schedule_id' => $schedule->id,
        'patient_admission_id' => $admission->id,
        'status' => 'overdue',
        'due_at' => now()->subMinutes(25),
    ]);

    $response = getJson(route('vitals-alerts.active'));

    $response->assertSuccessful()
        ->assertJsonPath('alerts.0.time_overdue_minutes', fn ($value) => $value >= 9 && $value <= 10);
});
