<?php

use App\Models\Drug;
use App\Models\MedicationAdministration;
use App\Models\PatientAdmission;
use App\Models\Prescription;
use App\Models\User;
use App\Models\WardRound;
use App\Services\MedicationScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Fake events to prevent charge creation side effects
    Event::fake();

    // Create permissions
    Permission::firstOrCreate(['name' => 'administer medications']);
    Permission::firstOrCreate(['name' => 'view medication administrations']);

    // Create user with necessary permissions
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('administer medications');
    $this->user->givePermissionTo('view medication administrations');

    // Create patient admission
    $this->admission = PatientAdmission::factory()->create([
        'admitted_at' => now()->subDays(5),
    ]);

    // Create doctor for ward round
    $this->doctor = User::factory()->create();

    // Create drug
    $this->drug = Drug::factory()->create([
        'name' => 'Amoxicillin',
        'strength' => '500mg',
    ]);
});

describe('Backdated Ward Round Medication Schedule', function () {
    it('generates schedule starting from backdated ward round date', function () {
        $backdatedDate = now()->subDays(3);

        // Create backdated ward round
        $wardRound = WardRound::factory()->create([
            'patient_admission_id' => $this->admission->id,
            'doctor_id' => $this->doctor->id,
            'day_number' => 1,
            'round_datetime' => $backdatedDate,
        ]);

        // Create prescription with schedule pattern
        $prescription = Prescription::factory()->create([
            'prescribable_type' => WardRound::class,
            'prescribable_id' => $wardRound->id,
            'drug_id' => $this->drug->id,
            'frequency' => 'TDS',
            'duration' => '5 days',
            'dose_quantity' => '1',
            'schedule_pattern' => [
                'day_1' => ['06:00', '14:00', '22:00'],
                'subsequent' => ['06:00', '14:00', '22:00'],
            ],
        ]);

        // Generate schedule
        $service = app(MedicationScheduleService::class);
        $service->generateScheduleFromPattern($prescription);

        // Get all scheduled administrations
        $administrations = MedicationAdministration::where('prescription_id', $prescription->id)
            ->orderBy('scheduled_time')
            ->get();

        // Should have 15 administrations (3 per day x 5 days)
        expect($administrations)->toHaveCount(15);

        // First administration should be on the backdated date, not today
        $firstAdmin = $administrations->first();
        expect($firstAdmin->scheduled_time->toDateString())->toBe($backdatedDate->toDateString());

        // Verify first day times
        $firstDayAdmins = $administrations->filter(function ($admin) use ($backdatedDate) {
            return $admin->scheduled_time->toDateString() === $backdatedDate->toDateString();
        });
        expect($firstDayAdmins)->toHaveCount(3);
    });

    it('allows marking past scheduled doses as given', function () {
        actingAs($this->user);

        $backdatedDate = now()->subDays(2);

        // Create backdated ward round
        $wardRound = WardRound::factory()->create([
            'patient_admission_id' => $this->admission->id,
            'doctor_id' => $this->doctor->id,
            'day_number' => 1,
            'round_datetime' => $backdatedDate,
        ]);

        // Create prescription
        $prescription = Prescription::factory()->create([
            'prescribable_type' => WardRound::class,
            'prescribable_id' => $wardRound->id,
            'drug_id' => $this->drug->id,
            'frequency' => 'BID',
            'duration' => '3 days',
            'dose_quantity' => '2',
            'schedule_pattern' => [
                'day_1' => ['08:00', '20:00'],
                'subsequent' => ['08:00', '20:00'],
            ],
        ]);

        // Generate schedule
        $service = app(MedicationScheduleService::class);
        $service->generateScheduleFromPattern($prescription);

        // Get a past scheduled administration
        $pastAdmin = MedicationAdministration::where('prescription_id', $prescription->id)
            ->where('scheduled_time', '<', now())
            ->where('status', 'scheduled')
            ->first();

        expect($pastAdmin)->not->toBeNull();
        expect($pastAdmin->scheduled_time->toDateString())->toBe($backdatedDate->toDateString());

        // Administer the past medication
        $response = $this->post("/admissions/{$pastAdmin->id}/administer", [
            'dosage_given' => '2 tablets',
            'route' => 'oral',
            'notes' => 'Administered during power outage, documented later',
        ]);

        $response->assertRedirect();

        // Verify it was marked as given
        $pastAdmin->refresh();
        expect($pastAdmin->status)->toBe('given');
        expect($pastAdmin->administered_by_id)->toBe($this->user->id);
    });

    it('generates smart defaults based on ward round date not current time', function () {
        $backdatedDate = now()->subDays(2)->setTime(10, 0);

        // Create backdated ward round
        $wardRound = WardRound::factory()->create([
            'patient_admission_id' => $this->admission->id,
            'doctor_id' => $this->doctor->id,
            'day_number' => 1,
            'round_datetime' => $backdatedDate,
        ]);

        // Create prescription
        $prescription = Prescription::factory()->create([
            'prescribable_type' => WardRound::class,
            'prescribable_id' => $wardRound->id,
            'drug_id' => $this->drug->id,
            'frequency' => 'TDS',
            'duration' => '5 days',
            'dose_quantity' => '1',
        ]);

        actingAs($this->user);

        // Get smart defaults - should use ward round date
        $response = $this->getJson("/api/prescriptions/{$prescription->id}/smart-defaults");

        $response->assertOk();
        $defaults = $response->json('defaults');

        // Day 1 times should be based on 10:00 AM reference time
        // For TDS at 10:00, should get times from 10:00 onwards
        expect($defaults)->toHaveKey('day_1');
        expect($defaults)->toHaveKey('subsequent');
    });

    it('uses today for non-backdated ward rounds', function () {
        // Create ward round with today's date
        $wardRound = WardRound::factory()->create([
            'patient_admission_id' => $this->admission->id,
            'doctor_id' => $this->doctor->id,
            'day_number' => 1,
            'round_datetime' => now(),
        ]);

        // Create prescription
        $prescription = Prescription::factory()->create([
            'prescribable_type' => WardRound::class,
            'prescribable_id' => $wardRound->id,
            'drug_id' => $this->drug->id,
            'frequency' => 'BID',
            'duration' => '3 days',
            'dose_quantity' => '1',
            'schedule_pattern' => [
                'day_1' => ['08:00', '20:00'],
                'subsequent' => ['08:00', '20:00'],
            ],
        ]);

        // Generate schedule
        $service = app(MedicationScheduleService::class);
        $service->generateScheduleFromPattern($prescription);

        // First administration should be today
        $firstAdmin = MedicationAdministration::where('prescription_id', $prescription->id)
            ->orderBy('scheduled_time')
            ->first();

        expect($firstAdmin->scheduled_time->toDateString())->toBe(now()->toDateString());
    });
});
