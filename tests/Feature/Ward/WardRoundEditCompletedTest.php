<?php

use App\Models\Consultation;
use App\Models\Department;
use App\Models\Drug;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\PatientCheckin;
use App\Models\Prescription;
use App\Models\User;
use App\Models\WardRound;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->department = Department::factory()->create();

    $doctorRole = Role::firstOrCreate(['name' => 'doctor']);
    $permissions = [
        'ward_rounds.view',
        'ward_rounds.create',
        'ward_rounds.update',
    ];
    foreach ($permissions as $permName) {
        $perm = Permission::firstOrCreate(['name' => $permName]);
        $doctorRole->givePermissionTo($perm);
    }

    $this->doctor = User::factory()->create();
    $this->doctor->assignRole('doctor');
    $this->doctor->departments()->attach($this->department->id);
    $this->patient = Patient::factory()->create();

    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $this->patient->id,
        'department_id' => $this->department->id,
    ]);
    $this->consultation = Consultation::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'doctor_id' => $this->doctor->id,
        'status' => 'completed',
    ]);
    $this->admission = PatientAdmission::factory()->create([
        'patient_id' => $this->patient->id,
        'consultation_id' => $this->consultation->id,
    ]);
});

it('allows editing a completed ward round within 24 hours', function () {
    $wardRound = WardRound::factory()->completed()->create([
        'patient_admission_id' => $this->admission->id,
        'doctor_id' => $this->doctor->id,
        'created_at' => now()->subHours(2),
    ]);

    $response = $this->actingAs($this->doctor)
        ->get("/admissions/{$this->admission->id}/ward-rounds/{$wardRound->id}/edit");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Ward/WardRoundCreate'));
});

it('allows auto-saving a completed ward round within 24 hours', function () {
    $wardRound = WardRound::factory()->completed()->create([
        'patient_admission_id' => $this->admission->id,
        'doctor_id' => $this->doctor->id,
        'created_at' => now()->subHours(2),
    ]);

    $response = $this->actingAs($this->doctor)
        ->patch("/admissions/{$this->admission->id}/ward-rounds/{$wardRound->id}", [
            'presenting_complaint' => 'Updated complaint on completed round',
        ]);

    $response->assertRedirect();
    $wardRound->refresh();
    expect($wardRound->presenting_complaint)->toBe('Updated complaint on completed round');
});

it('allows adding a prescription to a completed ward round within 24 hours', function () {
    $wardRound = WardRound::factory()->completed()->create([
        'patient_admission_id' => $this->admission->id,
        'doctor_id' => $this->doctor->id,
        'created_at' => now()->subHours(2),
    ]);

    $drug = Drug::factory()->create(['is_active' => true]);

    $response = $this->actingAs($this->doctor)
        ->post("/admissions/{$this->admission->id}/ward-rounds/{$wardRound->id}/prescriptions", [
            'medication_name' => $drug->name,
            'drug_id' => $drug->id,
            'dose_quantity' => '500mg',
            'frequency' => 'Twice daily',
            'duration' => '5 days',
            'quantity_to_dispense' => 10,
            'instructions' => 'After meals',
        ]);

    $response->assertRedirect();
    expect(Prescription::where('prescribable_type', WardRound::class)
        ->where('prescribable_id', $wardRound->id)
        ->count())->toBe(1);
});

it('denies editing a completed ward round after 24 hours for non-admin', function () {
    $wardRound = WardRound::factory()->completed()->create([
        'patient_admission_id' => $this->admission->id,
        'doctor_id' => $this->doctor->id,
        'created_at' => now()->subHours(25),
    ]);

    $response = $this->actingAs($this->doctor)
        ->get("/admissions/{$this->admission->id}/ward-rounds/{$wardRound->id}/edit");

    $response->assertForbidden();
});

it('allows admin to edit a completed ward round after 24 hours', function () {
    $adminRole = Role::firstOrCreate(['name' => 'Admin']);

    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $wardRound = WardRound::factory()->completed()->create([
        'patient_admission_id' => $this->admission->id,
        'doctor_id' => $this->doctor->id,
        'created_at' => now()->subHours(48),
    ]);

    $response = $this->actingAs($admin)
        ->get("/admissions/{$this->admission->id}/ward-rounds/{$wardRound->id}/edit");

    $response->assertOk();
});
