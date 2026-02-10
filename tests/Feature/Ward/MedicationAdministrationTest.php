<?php

use App\Models\Consultation;
use App\Models\Drug;
use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\PatientCheckin;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions
    Permission::firstOrCreate(['name' => 'view medication administrations']);
    Permission::firstOrCreate(['name' => 'administer medications']);
    Permission::firstOrCreate(['name' => 'delete medication administrations']);
    Permission::firstOrCreate(['name' => 'medications.delete']);
    Permission::firstOrCreate(['name' => 'medications.delete-old']);
    Permission::firstOrCreate(['name' => 'medications.edit-timestamp']);

    // Create user with permissions
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('view medication administrations');
    $this->user->givePermissionTo('administer medications');

    // Create ward and admission
    $this->ward = Ward::factory()->create();
    $this->patient = Patient::factory()->create();
    $this->checkin = PatientCheckin::factory()->create(['patient_id' => $this->patient->id]);
    $this->consultation = Consultation::factory()->create(['patient_checkin_id' => $this->checkin->id]);
    $this->admission = PatientAdmission::factory()->create([
        'patient_id' => $this->patient->id,
        'consultation_id' => $this->consultation->id,
        'ward_id' => $this->ward->id,
        'status' => 'admitted',
    ]);

    // Create drug and prescription (without events to avoid charge creation)
    $this->drug = Drug::factory()->create(['name' => 'Paracetamol', 'strength' => '500mg']);
    $this->prescription = Prescription::withoutEvents(function () {
        return Prescription::factory()->create([
            'consultation_id' => $this->consultation->id,
            'drug_id' => $this->drug->id,
            'medication_name' => 'Paracetamol',
            'dose_quantity' => '2 tablets',
            'frequency' => 'TDS',
            'duration' => '5 days',
            'status' => 'prescribed',
        ]);
    });
});

describe('Medication Administration Recording', function () {
    it('allows nurse to record medication given', function () {
        $initialCount = MedicationAdministration::count();

        $response = $this->actingAs($this->user)
            ->post("/admissions/{$this->admission->id}/medications", [
                'prescription_id' => $this->prescription->id,
                'dosage_given' => '2 tablets (1000mg)',
                'route' => 'oral',
                'notes' => 'Patient tolerated well',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        expect(MedicationAdministration::count())->toBe($initialCount + 1);

        $administration = MedicationAdministration::where('prescription_id', $this->prescription->id)
            ->where('status', 'given')
            ->latest()
            ->first();
        expect($administration)->not->toBeNull()
            ->and($administration->dosage_given)->toBe('2 tablets (1000mg)')
            ->and($administration->route)->toBe('oral')
            ->and($administration->administered_by_id)->toBe($this->user->id)
            ->and($administration->administered_at)->not->toBeNull();
    });

    it('allows nurse with permission to record medication with custom administered_at time', function () {
        $this->user->givePermissionTo('medications.edit-timestamp');
        $customTime = now()->subDays(2)->setTime(14, 30);

        $response = $this->actingAs($this->user)
            ->post("/admissions/{$this->admission->id}/medications", [
                'prescription_id' => $this->prescription->id,
                'dosage_given' => '2 tablets',
                'route' => 'oral',
                'administered_at' => $customTime->toDateTimeString(),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $administration = MedicationAdministration::where('prescription_id', $this->prescription->id)
            ->where('status', 'given')
            ->latest()
            ->first();

        expect($administration)->not->toBeNull()
            ->and($administration->administered_at->format('Y-m-d H:i'))->toBe($customTime->format('Y-m-d H:i'));
    });

    it('ignores custom timestamp when nurse lacks permission', function () {
        // User does NOT have medications.edit-timestamp permission
        $customTime = now()->subDays(2)->setTime(14, 30);

        $response = $this->actingAs($this->user)
            ->post("/admissions/{$this->admission->id}/medications", [
                'prescription_id' => $this->prescription->id,
                'dosage_given' => '2 tablets',
                'route' => 'oral',
                'administered_at' => $customTime->toDateTimeString(),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $administration = MedicationAdministration::where('prescription_id', $this->prescription->id)
            ->where('status', 'given')
            ->latest()
            ->first();

        expect($administration)->not->toBeNull();
        // Timestamp should be close to now, not the custom timestamp
        expect($administration->administered_at->diffInMinutes(now()))->toBeLessThan(2);
    });

    it('allows nurse with permission to record medication with future administered_at time', function () {
        $this->user->givePermissionTo('medications.edit-timestamp');
        $futureTime = now()->addHour();

        $response = $this->actingAs($this->user)
            ->post("/admissions/{$this->admission->id}/medications", [
                'prescription_id' => $this->prescription->id,
                'dosage_given' => '2 tablets',
                'route' => 'oral',
                'administered_at' => $futureTime->toDateTimeString(),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $administration = MedicationAdministration::where('prescription_id', $this->prescription->id)
            ->where('status', 'given')
            ->latest()
            ->first();

        expect($administration)->not->toBeNull()
            ->and($administration->administered_at->format('Y-m-d H:i'))->toBe($futureTime->format('Y-m-d H:i'));
    });

    it('allows nurse to record medication held', function () {
        $response = $this->actingAs($this->user)
            ->post("/admissions/{$this->admission->id}/medications/hold", [
                'prescription_id' => $this->prescription->id,
                'notes' => 'Patient NPO for surgery tomorrow',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $administration = MedicationAdministration::where('prescription_id', $this->prescription->id)
            ->where('status', 'held')
            ->first();
        expect($administration)->not->toBeNull()
            ->and($administration->status)->toBe('held')
            ->and($administration->notes)->toBe('Patient NPO for surgery tomorrow');
    });

    it('allows nurse to record medication refused', function () {
        $response = $this->actingAs($this->user)
            ->post("/admissions/{$this->admission->id}/medications/refuse", [
                'prescription_id' => $this->prescription->id,
                'notes' => 'Patient says medication causes nausea',
            ]);

        $response->assertRedirect();

        $administration = MedicationAdministration::where('status', 'refused')->first();
        expect($administration)->not->toBeNull()
            ->and($administration->status)->toBe('refused');
    });

    it('allows nurse to record medication omitted', function () {
        $response = $this->actingAs($this->user)
            ->post("/admissions/{$this->admission->id}/medications/omit", [
                'prescription_id' => $this->prescription->id,
                'notes' => 'Medication not available in pharmacy',
            ]);

        $response->assertRedirect();

        $administration = MedicationAdministration::where('status', 'omitted')->first();
        expect($administration)->not->toBeNull()
            ->and($administration->status)->toBe('omitted');
    });

    it('requires notes when holding medication', function () {
        $response = $this->actingAs($this->user)
            ->post("/admissions/{$this->admission->id}/medications/hold", [
                'prescription_id' => $this->prescription->id,
                'notes' => 'short', // Too short
            ]);

        $response->assertSessionHasErrors('notes');
    });

    it('prevents recording for discontinued prescription', function () {
        $this->prescription->update([
            'discontinued_at' => now(),
            'discontinued_by_id' => $this->user->id,
        ]);

        $initialCount = MedicationAdministration::count();

        $response = $this->actingAs($this->user)
            ->post("/admissions/{$this->admission->id}/medications", [
                'prescription_id' => $this->prescription->id,
                'dosage_given' => '2 tablets',
                'route' => 'oral',
            ]);

        $response->assertSessionHasErrors('prescription_id');
        expect(MedicationAdministration::count())->toBe($initialCount);
    });

    it('prevents recording for prescription from different admission', function () {
        $otherAdmission = PatientAdmission::factory()->create([
            'ward_id' => $this->ward->id,
            'status' => 'admitted',
        ]);

        $response = $this->actingAs($this->user)
            ->post("/admissions/{$otherAdmission->id}/medications", [
                'prescription_id' => $this->prescription->id,
                'dosage_given' => '2 tablets',
                'route' => 'oral',
            ]);

        $response->assertSessionHasErrors('prescription_id');
    });
});

describe('Medication Administration Index', function () {
    it('returns active prescriptions with today count', function () {
        // Record some administrations today
        MedicationAdministration::factory()->create([
            'prescription_id' => $this->prescription->id,
            'patient_admission_id' => $this->admission->id,
            'administered_by_id' => $this->user->id,
            'administered_at' => now(),
            'status' => 'given',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/admissions/{$this->admission->id}/medications");

        $response->assertOk()
            ->assertJsonStructure([
                'prescriptions',
                'today_administrations',
            ]);
    });
});

describe('Prescription Helper Methods', function () {
    it('calculates expected doses per day correctly', function () {
        $frequencies = [
            'OD' => 1,
            'BD' => 2,
            'BID' => 2,
            'TDS' => 3,
            'TID' => 3,
            'QDS' => 4,
            'QID' => 4,
            'Q6H' => 4,
            'Q8H' => 3,
            'Q12H' => 2,
            'PRN' => 0,
        ];

        foreach ($frequencies as $frequency => $expected) {
            $prescription = Prescription::withoutEvents(function () use ($frequency) {
                return Prescription::factory()->create([
                    'consultation_id' => $this->consultation->id,
                    'frequency' => $frequency,
                ]);
            });

            expect($prescription->getExpectedDosesPerDay())->toBe($expected, "Failed for frequency: {$frequency}");
        }
    });

    it('identifies PRN prescriptions', function () {
        $prnPrescription = Prescription::withoutEvents(function () {
            return Prescription::factory()->create([
                'consultation_id' => $this->consultation->id,
                'frequency' => 'PRN',
            ]);
        });

        $regularPrescription = Prescription::withoutEvents(function () {
            return Prescription::factory()->create([
                'consultation_id' => $this->consultation->id,
                'frequency' => 'TDS',
            ]);
        });

        expect($prnPrescription->isPrn())->toBeTrue()
            ->and($regularPrescription->isPrn())->toBeFalse();
    });

    it('counts today administrations', function () {
        // Create 2 administrations today
        MedicationAdministration::factory()->count(2)->create([
            'prescription_id' => $this->prescription->id,
            'patient_admission_id' => $this->admission->id,
            'administered_at' => now(),
            'status' => 'given',
        ]);

        // Create 1 administration yesterday (should not count)
        MedicationAdministration::factory()->create([
            'prescription_id' => $this->prescription->id,
            'patient_admission_id' => $this->admission->id,
            'administered_at' => now()->subDay(),
            'status' => 'given',
        ]);

        expect($this->prescription->getTodayAdministrationCount())->toBe(2);
    });
});

describe('Authorization', function () {
    it('requires permission to view medication administrations', function () {
        $userWithoutPermission = User::factory()->create();

        $response = $this->actingAs($userWithoutPermission)
            ->getJson("/admissions/{$this->admission->id}/medications");

        $response->assertForbidden();
    });

    it('requires permission to record medication', function () {
        $userWithoutPermission = User::factory()->create();

        $response = $this->actingAs($userWithoutPermission)
            ->post("/admissions/{$this->admission->id}/medications", [
                'prescription_id' => $this->prescription->id,
                'dosage_given' => '2 tablets',
            ]);

        $response->assertForbidden();
    });

    it('allows user with medications.delete to delete recent MAR record', function () {
        $this->user->givePermissionTo('medications.delete');

        $administration = MedicationAdministration::factory()->create([
            'prescription_id' => $this->prescription->id,
            'patient_admission_id' => $this->admission->id,
            'administered_at' => now()->subHours(1),
            'status' => 'given',
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/admissions/{$this->admission->id}/medications/{$administration->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        expect($administration->fresh()->trashed())->toBeTrue();
    });

    it('prevents user with only medications.delete from deleting old MAR record', function () {
        $this->user->givePermissionTo('medications.delete');

        $administration = MedicationAdministration::factory()->create([
            'prescription_id' => $this->prescription->id,
            'patient_admission_id' => $this->admission->id,
            'administered_at' => now()->subDays(5),
            'status' => 'given',
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/admissions/{$this->admission->id}/medications/{$administration->id}");

        $response->assertForbidden();
        expect($administration->fresh()->trashed())->toBeFalse();
    });

    it('allows user with medications.delete-old to delete old MAR record', function () {
        $this->user->givePermissionTo('medications.delete-old');

        $administration = MedicationAdministration::factory()->create([
            'prescription_id' => $this->prescription->id,
            'patient_admission_id' => $this->admission->id,
            'administered_at' => now()->subDays(5),
            'status' => 'given',
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/admissions/{$this->admission->id}/medications/{$administration->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        expect($administration->fresh()->trashed())->toBeTrue();
    });

    it('prevents user without any delete permission from deleting MAR record', function () {
        $userWithoutPermission = User::factory()->create();

        $administration = MedicationAdministration::factory()->create([
            'prescription_id' => $this->prescription->id,
            'patient_admission_id' => $this->admission->id,
            'administered_at' => now()->subHours(1),
            'status' => 'given',
        ]);

        $response = $this->actingAs($userWithoutPermission)
            ->delete("/admissions/{$this->admission->id}/medications/{$administration->id}");

        $response->assertForbidden();
    });
});
