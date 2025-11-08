<?php

use App\Models\Drug;
use App\Models\MedicationAdministration;
use App\Models\PatientAdmission;
use App\Models\Prescription;
use App\Models\User;
use App\Models\WardRound;
use App\Services\MedicationScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions
    Permission::create(['name' => 'administer medications']);
    Permission::create(['name' => 'view medication administrations']);

    // Create user with necessary permissions
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('administer medications');
    $this->user->givePermissionTo('view medication administrations');

    // Create patient admission
    $this->admission = PatientAdmission::factory()->create();

    // Create doctor for ward round
    $doctor = User::factory()->create();

    // Create ward round
    $this->wardRound = WardRound::factory()->create([
        'patient_admission_id' => $this->admission->id,
        'doctor_id' => $doctor->id,
        'day_number' => 1,
        'round_datetime' => now(),
    ]);

    // Create drug
    $this->drug = Drug::factory()->create([
        'name' => 'Paracetamol',
        'strength' => '500mg',
    ]);
});

it('allows administering medication from configured schedule', function () {
    actingAs($this->user);

    // Create prescription with configured schedule
    $prescription = Prescription::factory()->create([
        'prescribable_type' => WardRound::class,
        'prescribable_id' => $this->wardRound->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'BID',
        'duration' => '5 days',
        'dose_quantity' => '2',
        'schedule_pattern' => [
            'day_1' => ['10:00', '18:00'],
            'subsequent' => ['06:00', '18:00'],
        ],
    ]);

    // Generate schedule from pattern
    $service = app(MedicationScheduleService::class);
    $service->generateScheduleFromPattern($prescription);

    // Get first scheduled administration
    $administration = MedicationAdministration::where('prescription_id', $prescription->id)
        ->where('status', 'scheduled')
        ->first();

    expect($administration)->not->toBeNull();

    // Administer the medication
    $response = $this->post("/admissions/{$administration->id}/administer", [
        'dosage_given' => '2 tablets (1000mg)',
        'route' => 'oral',
        'notes' => 'Patient tolerated well',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Verify administration was recorded
    $administration->refresh();
    expect($administration->status)->toBe('given')
        ->and($administration->administered_by_id)->toBe($this->user->id)
        ->and($administration->administered_at)->not->toBeNull()
        ->and($administration->dosage_given)->toBe('2 tablets (1000mg)')
        ->and($administration->route)->toBe('oral')
        ->and($administration->notes)->toBe('Patient tolerated well');
});

it('allows holding medication from configured schedule', function () {
    actingAs($this->user);

    // Create prescription with configured schedule
    $prescription = Prescription::factory()->create([
        'prescribable_type' => WardRound::class,
        'prescribable_id' => $this->wardRound->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'TID',
        'duration' => '3 days',
        'dose_quantity' => '1',
        'schedule_pattern' => [
            'day_1' => ['14:00', '22:00'],
            'subsequent' => ['06:00', '14:00', '22:00'],
        ],
    ]);

    // Generate schedule from pattern
    $service = app(MedicationScheduleService::class);
    $service->generateScheduleFromPattern($prescription);

    // Get first scheduled administration
    $administration = MedicationAdministration::where('prescription_id', $prescription->id)
        ->where('status', 'scheduled')
        ->first();

    expect($administration)->not->toBeNull();

    // Hold the medication
    $response = $this->post("/admissions/{$administration->id}/hold", [
        'notes' => 'Patient NPO for surgery',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Verify medication was held
    $administration->refresh();
    expect($administration->status)->toBe('held')
        ->and($administration->administered_by_id)->toBe($this->user->id)
        ->and($administration->notes)->toBe('Patient NPO for surgery');
});

it('allows refusing medication from configured schedule', function () {
    actingAs($this->user);

    // Create prescription with configured schedule
    $prescription = Prescription::factory()->create([
        'prescribable_type' => WardRound::class,
        'prescribable_id' => $this->wardRound->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'QID',
        'duration' => '7 days',
        'dose_quantity' => '1',
        'schedule_pattern' => [
            'day_1' => ['12:00', '18:00', '00:00'],
            'subsequent' => ['06:00', '12:00', '18:00', '00:00'],
        ],
    ]);

    // Generate schedule from pattern
    $service = app(MedicationScheduleService::class);
    $service->generateScheduleFromPattern($prescription);

    // Get first scheduled administration
    $administration = MedicationAdministration::where('prescription_id', $prescription->id)
        ->where('status', 'scheduled')
        ->first();

    expect($administration)->not->toBeNull();

    // Refuse the medication
    $response = $this->post("/admissions/{$administration->id}/refuse", [
        'notes' => 'Patient refused due to nausea',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Verify medication was refused
    $administration->refresh();
    expect($administration->status)->toBe('refused')
        ->and($administration->administered_by_id)->toBe($this->user->id)
        ->and($administration->notes)->toBe('Patient refused due to nausea');
});

it('prevents administering medication from discontinued prescription', function () {
    actingAs($this->user);

    // Create prescription with configured schedule
    $prescription = Prescription::factory()->create([
        'prescribable_type' => WardRound::class,
        'prescribable_id' => $this->wardRound->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'BID',
        'duration' => '5 days',
        'dose_quantity' => '2',
        'schedule_pattern' => [
            'day_1' => ['10:00', '18:00'],
            'subsequent' => ['06:00', '18:00'],
        ],
    ]);

    // Generate schedule from pattern
    $service = app(MedicationScheduleService::class);
    $service->generateScheduleFromPattern($prescription);

    // Discontinue the prescription
    $service->discontinuePrescription($prescription, $this->user, 'Switching to alternative medication');

    // Try to get a scheduled administration (should be cancelled)
    $administration = MedicationAdministration::where('prescription_id', $prescription->id)
        ->where('status', 'scheduled')
        ->where('scheduled_time', '>', now())
        ->first();

    expect($administration)->toBeNull();

    // Verify all future administrations were cancelled
    $cancelledCount = MedicationAdministration::where('prescription_id', $prescription->id)
        ->where('status', 'cancelled')
        ->count();

    expect($cancelledCount)->toBeGreaterThan(0);
});

it('preserves given medications when prescription is discontinued', function () {
    actingAs($this->user);

    // Create prescription with configured schedule
    $prescription = Prescription::factory()->create([
        'prescribable_type' => WardRound::class,
        'prescribable_id' => $this->wardRound->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'BID',
        'duration' => '5 days',
        'dose_quantity' => '2',
        'schedule_pattern' => [
            'day_1' => ['10:00', '18:00'],
            'subsequent' => ['06:00', '18:00'],
        ],
    ]);

    // Generate schedule from pattern
    $service = app(MedicationScheduleService::class);
    $service->generateScheduleFromPattern($prescription);

    // Administer first dose
    $firstAdmin = MedicationAdministration::where('prescription_id', $prescription->id)
        ->where('status', 'scheduled')
        ->orderBy('scheduled_time')
        ->first();

    $firstAdmin->update([
        'status' => 'given',
        'administered_at' => now(),
        'administered_by_id' => $this->user->id,
        'dosage_given' => '2 tablets',
        'route' => 'oral',
    ]);

    // Discontinue the prescription
    $service->discontinuePrescription($prescription, $this->user, 'Patient discharged');

    // Verify given medication is preserved
    $firstAdmin->refresh();
    expect($firstAdmin->status)->toBe('given');

    // Verify future medications are cancelled
    $futureScheduled = MedicationAdministration::where('prescription_id', $prescription->id)
        ->where('status', 'scheduled')
        ->where('scheduled_time', '>', now())
        ->count();

    expect($futureScheduled)->toBe(0);
});

it('allows administering adjusted medication times', function () {
    actingAs($this->user);

    // Create prescription with configured schedule
    $prescription = Prescription::factory()->create([
        'prescribable_type' => WardRound::class,
        'prescribable_id' => $this->wardRound->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'BID',
        'duration' => '3 days',
        'dose_quantity' => '1',
        'schedule_pattern' => [
            'day_1' => ['10:00', '18:00'],
            'subsequent' => ['06:00', '18:00'],
        ],
    ]);

    // Generate schedule from pattern
    $service = app(MedicationScheduleService::class);
    $service->generateScheduleFromPattern($prescription);

    // Get first scheduled administration
    $administration = MedicationAdministration::where('prescription_id', $prescription->id)
        ->where('status', 'scheduled')
        ->first();

    // Adjust the time to a past time (so it's due now)
    $newTime = now()->subMinutes(10);
    $service->adjustScheduleTime($administration, $newTime, $this->user, 'Patient requested earlier time');

    $administration->refresh();
    expect($administration->is_adjusted)->toBeTrue();

    // Administer the medication at adjusted time
    $response = $this->post("/admissions/{$administration->id}/administer", [
        'dosage_given' => '1 tablet',
        'route' => 'oral',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Verify administration was recorded
    $administration->refresh();
    expect($administration->status)->toBe('given')
        ->and($administration->is_adjusted)->toBeTrue();
});

it('works with reconfigured schedules', function () {
    actingAs($this->user);

    // Create prescription with configured schedule
    $prescription = Prescription::factory()->create([
        'prescribable_type' => WardRound::class,
        'prescribable_id' => $this->wardRound->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'BID',
        'duration' => '5 days',
        'dose_quantity' => '2',
        'schedule_pattern' => [
            'day_1' => ['10:00', '18:00'],
            'subsequent' => ['06:00', '18:00'],
        ],
    ]);

    // Generate schedule from pattern
    $service = app(MedicationScheduleService::class);
    $service->generateScheduleFromPattern($prescription);

    // Administer first dose
    $firstAdmin = MedicationAdministration::where('prescription_id', $prescription->id)
        ->where('status', 'scheduled')
        ->orderBy('scheduled_time')
        ->first();

    $firstAdmin->update([
        'status' => 'given',
        'administered_at' => now(),
        'administered_by_id' => $this->user->id,
        'dosage_given' => '2 tablets',
        'route' => 'oral',
    ]);

    // Reconfigure schedule with new times (using past times so they're due)
    $pastTime = now()->subHours(2)->format('H:i');
    $newPattern = [
        'day_1' => [$pastTime, '20:00'],
        'subsequent' => ['08:00', '20:00'],
    ];

    $service->reconfigureSchedule($prescription, $newPattern, $this->user);

    // Verify given medication is preserved
    $firstAdmin->refresh();
    expect($firstAdmin->status)->toBe('given');

    // Verify new schedule was created
    $newScheduled = MedicationAdministration::where('prescription_id', $prescription->id)
        ->where('status', 'scheduled')
        ->count();

    expect($newScheduled)->toBeGreaterThan(0);

    // Administer from new schedule (get a due medication)
    $newAdmin = MedicationAdministration::where('prescription_id', $prescription->id)
        ->where('status', 'scheduled')
        ->where('scheduled_time', '<=', now())
        ->orderBy('scheduled_time')
        ->first();

    expect($newAdmin)->not->toBeNull();

    $response = $this->post("/admissions/{$newAdmin->id}/administer", [
        'dosage_given' => '2 tablets',
        'route' => 'oral',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $newAdmin->refresh();
    expect($newAdmin->status)->toBe('given');
});

it('prevents administering cancelled medications', function () {
    actingAs($this->user);

    // Create prescription with configured schedule
    $prescription = Prescription::factory()->create([
        'prescribable_type' => WardRound::class,
        'prescribable_id' => $this->wardRound->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'BID',
        'duration' => '3 days',
        'dose_quantity' => '1',
        'schedule_pattern' => [
            'day_1' => ['10:00', '18:00'],
            'subsequent' => ['06:00', '18:00'],
        ],
    ]);

    // Generate schedule from pattern
    $service = app(MedicationScheduleService::class);
    $service->generateScheduleFromPattern($prescription);

    // Get first scheduled administration and cancel it
    $administration = MedicationAdministration::where('prescription_id', $prescription->id)
        ->where('status', 'scheduled')
        ->first();

    $administration->update(['status' => 'cancelled']);

    // Try to administer cancelled medication
    $response = $this->post("/admissions/{$administration->id}/administer", [
        'dosage_given' => '1 tablet',
        'route' => 'oral',
    ]);

    // Should be forbidden since status is not 'scheduled'
    $response->assertForbidden();
});
