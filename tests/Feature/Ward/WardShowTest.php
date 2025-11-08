<?php

use App\Models\Bed;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\User;
use App\Models\VitalsSchedule;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);
});

it('includes vitals schedule data in ward show response', function () {
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
        'interval_minutes' => 240,
        'next_due_at' => now()->addHours(2),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $response = $this->get(route('wards.show', $ward));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/Show')
        ->has('ward.admissions.0.vitals_schedule_status')
        ->where('ward.admissions.0.vitals_schedule_status.status', 'upcoming')
        ->where('ward.admissions.0.vitals_schedule_status.interval_minutes', 240)
    );
});

it('includes vitals schedule statistics in ward stats', function () {
    $ward = Ward::factory()->create();
    $bed1 = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $bed2 = Bed::create([
        'ward_id' => $ward->id,
        'bed_number' => '02',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);

    $patient1 = Patient::factory()->create();
    $admission1 = PatientAdmission::factory()->create([
        'patient_id' => $patient1->id,
        'bed_id' => $bed1->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    $patient2 = Patient::factory()->create();
    $admission2 = PatientAdmission::factory()->create([
        'patient_id' => $patient2->id,
        'bed_id' => $bed2->id,
        'ward_id' => $ward->id,
        'status' => 'admitted',
    ]);

    // Create schedule for first admission (upcoming)
    VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission1->id,
        'interval_minutes' => 240,
        'next_due_at' => now()->addHours(2),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    // Create schedule for second admission (due)
    VitalsSchedule::factory()->create([
        'patient_admission_id' => $admission2->id,
        'interval_minutes' => 240,
        'next_due_at' => now()->subMinutes(5),
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $response = $this->get(route('wards.show', $ward));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/Show')
        ->where('stats.scheduled_vitals_count', 2)
        ->where('stats.vitals_due_count', 1)
        ->where('stats.vitals_overdue_count', 0)
    );
});

it('handles admissions without vitals schedules', function () {
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

    $response = $this->get(route('wards.show', $ward));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Ward/Show')
        ->where('ward.admissions.0.vitals_schedule_status', null)
        ->where('stats.scheduled_vitals_count', 0)
        ->where('stats.vitals_due_count', 0)
        ->where('stats.vitals_overdue_count', 0)
    );
});
