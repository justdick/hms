<?php

use App\Models\Charge;
use App\Models\Consultation;
use App\Models\Department;
use App\Models\Drug;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\PatientCheckin;
use App\Models\Prescription;
use App\Models\User;
use App\Models\WardRound;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->department = Department::factory()->create();

    $doctorRole = Role::firstOrCreate(['name' => 'doctor']);
    foreach (['ward_rounds.view', 'ward_rounds.create', 'ward_rounds.update'] as $permName) {
        $perm = Permission::firstOrCreate(['name' => $permName]);
        $doctorRole->givePermissionTo($perm);
    }

    $this->doctor = User::factory()->create();
    $this->doctor->assignRole('doctor');
    $this->doctor->departments()->attach($this->department->id);

    $this->patient = Patient::factory()->create();
    $this->checkin = PatientCheckin::factory()->create([
        'patient_id' => $this->patient->id,
        'department_id' => $this->department->id,
    ]);
    $this->consultation = Consultation::factory()->create([
        'patient_checkin_id' => $this->checkin->id,
        'doctor_id' => $this->doctor->id,
        'status' => 'completed',
    ]);
    $this->admission = PatientAdmission::factory()->create([
        'patient_id' => $this->patient->id,
        'consultation_id' => $this->consultation->id,
    ]);

    $this->drug = Drug::factory()->create([
        'unit_price' => 100.00,
        'is_active' => true,
    ]);
});

it('sets charge date to prescription prescribed_at date, not today', function () {
    $futureDate = Carbon::now()->addDays(2)->startOfDay()->setTime(9, 0);

    $wardRound = WardRound::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'doctor_id' => $this->doctor->id,
        'round_datetime' => $futureDate,
    ]);

    $this->actingAs($this->doctor);

    // Create prescription via the observer (not withoutEvents)
    $prescription = Prescription::create([
        'prescribable_type' => WardRound::class,
        'prescribable_id' => $wardRound->id,
        'drug_id' => $this->drug->id,
        'medication_name' => $this->drug->name,
        'dose_quantity' => '1',
        'frequency' => 'Twice daily',
        'duration' => '5 days',
        'quantity' => 10,
        'quantity_to_dispense' => 10,
        'status' => 'prescribed',
        'prescribed_at' => $futureDate,
    ]);

    $charge = Charge::where('prescription_id', $prescription->id)->first();

    expect($charge)->not->toBeNull()
        ->and($charge->charged_at->toDateString())->toBe($futureDate->toDateString());
});

it('uses today when prescribed_at is null', function () {
    $wardRound = WardRound::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'doctor_id' => $this->doctor->id,
    ]);

    $this->actingAs($this->doctor);

    $prescription = Prescription::create([
        'prescribable_type' => WardRound::class,
        'prescribable_id' => $wardRound->id,
        'drug_id' => $this->drug->id,
        'medication_name' => $this->drug->name,
        'dose_quantity' => '1',
        'frequency' => 'Once daily',
        'duration' => '3 days',
        'quantity' => 3,
        'quantity_to_dispense' => 3,
        'status' => 'prescribed',
        'prescribed_at' => null,
    ]);

    $charge = Charge::where('prescription_id', $prescription->id)->first();

    expect($charge)->not->toBeNull()
        ->and($charge->charged_at->toDateString())->toBe(now()->toDateString());
});
