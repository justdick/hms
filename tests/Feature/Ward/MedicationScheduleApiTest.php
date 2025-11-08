<?php

use App\Models\Bed;
use App\Models\Consultation;
use App\Models\Drug;
use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\Prescription;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    // Create permissions
    Permission::create(['name' => 'administer medications']);
    Permission::create(['name' => 'manage prescriptions']);

    $this->user->givePermissionTo(['administer medications', 'manage prescriptions']);

    actingAs($this->user);

    // Create test data
    $this->ward = Ward::factory()->create();
    $this->bed = Bed::create([
        'ward_id' => $this->ward->id,
        'bed_number' => '01',
        'status' => 'occupied',
        'type' => 'standard',
        'is_active' => true,
    ]);
    $this->patient = Patient::factory()->create();
    $this->admission = PatientAdmission::factory()->create([
        'patient_id' => $this->patient->id,
        'bed_id' => $this->bed->id,
        'ward_id' => $this->ward->id,
        'status' => 'admitted',
    ]);
    $this->drug = Drug::factory()->create();
    $this->consultation = Consultation::factory()->create();
    $this->admission = PatientAdmission::factory()->create([
        'patient_id' => $this->patient->id,
        'consultation_id' => $this->consultation->id,
        'bed_id' => $this->bed->id,
        'ward_id' => $this->ward->id,
        'status' => 'admitted',
    ]);
    $this->prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'BID',
        'duration' => '5 days',
    ]);
});

it('returns all medication administrations for a prescription', function () {
    $administration = MedicationAdministration::factory()->create([
        'prescription_id' => $this->prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->addHours(2),
        'status' => 'scheduled',
    ]);

    $response = getJson(route('api.prescriptions.schedule', $this->prescription));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'prescription' => ['id', 'drug', 'discontinued_by'],
            'administrations' => [
                '*' => [
                    'id',
                    'scheduled_time',
                    'status',
                    'is_adjusted',
                    'schedule_adjustments',
                    'latest_adjustment',
                ],
            ],
        ]);

    // Verify the administration we created is in the response
    $administrationIds = collect($response->json('administrations'))->pluck('id')->toArray();
    expect($administrationIds)->toContain($administration->id);
});

it('adjusts medication schedule time successfully', function () {
    $administration = MedicationAdministration::factory()->create([
        'prescription_id' => $this->prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->addHours(2),
        'status' => 'scheduled',
        'is_adjusted' => false,
    ]);

    $newTime = now()->addHours(4);

    $response = patchJson(route('api.medication-administrations.adjust-time', $administration), [
        'scheduled_time' => $newTime->toISOString(),
        'reason' => 'Patient requested later time',
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Medication schedule adjusted successfully.',
        ]);

    $administration->refresh();
    expect($administration->is_adjusted)->toBeTrue()
        ->and($administration->scheduled_time->format('Y-m-d H:i'))->toBe($newTime->format('Y-m-d H:i'))
        ->and($administration->scheduleAdjustments)->toHaveCount(1)
        ->and($administration->scheduleAdjustments->first()->adjusted_by_id)->toBe($this->user->id)
        ->and($administration->scheduleAdjustments->first()->reason)->toBe('Patient requested later time');
});

it('cannot adjust medication that has already been given', function () {
    $administration = MedicationAdministration::factory()->create([
        'prescription_id' => $this->prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->subHours(2),
        'status' => 'given',
        'administered_at' => now()->subHours(1),
        'administered_by_id' => $this->user->id,
    ]);

    $response = patchJson(route('api.medication-administrations.adjust-time', $administration), [
        'scheduled_time' => now()->addHours(2)->toISOString(),
    ]);

    $response->assertForbidden();
});

it('cannot adjust to past time', function () {
    $administration = MedicationAdministration::factory()->create([
        'prescription_id' => $this->prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->addHours(2),
        'status' => 'scheduled',
    ]);

    $response = patchJson(route('api.medication-administrations.adjust-time', $administration), [
        'scheduled_time' => now()->subHours(1)->toISOString(),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['scheduled_time']);
});

it('requires reason to be under 500 characters', function () {
    $administration = MedicationAdministration::factory()->create([
        'prescription_id' => $this->prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->addHours(2),
        'status' => 'scheduled',
    ]);

    $response = patchJson(route('api.medication-administrations.adjust-time', $administration), [
        'scheduled_time' => now()->addHours(4)->toISOString(),
        'reason' => str_repeat('a', 501),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

it('returns adjustment history for a medication administration', function () {
    $administration = MedicationAdministration::factory()->create([
        'prescription_id' => $this->prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->addHours(2),
        'status' => 'scheduled',
        'is_adjusted' => true,
    ]);

    // Create adjustment history
    $administration->scheduleAdjustments()->create([
        'adjusted_by_id' => $this->user->id,
        'original_time' => now()->addHours(2),
        'adjusted_time' => now()->addHours(4),
        'reason' => 'Patient requested later time',
    ]);

    $response = getJson(route('api.medication-administrations.adjustment-history', $administration));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'administration' => ['id', 'scheduled_time'],
            'adjustments' => [
                '*' => [
                    'id',
                    'original_time',
                    'adjusted_time',
                    'reason',
                    'adjusted_by',
                ],
            ],
        ])
        ->assertJsonPath('adjustments.0.reason', 'Patient requested later time');
});

it('discontinues prescription successfully', function () {
    // Create future scheduled administrations
    $futureAdmin1 = MedicationAdministration::factory()->create([
        'prescription_id' => $this->prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->addHours(2),
        'status' => 'scheduled',
    ]);

    $futureAdmin2 = MedicationAdministration::factory()->create([
        'prescription_id' => $this->prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->addHours(14),
        'status' => 'scheduled',
    ]);

    // Create already given administration
    $givenAdmin = MedicationAdministration::factory()->create([
        'prescription_id' => $this->prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->subHours(2),
        'status' => 'given',
        'administered_at' => now()->subHours(1),
        'administered_by_id' => $this->user->id,
    ]);

    $response = postJson(route('api.prescriptions.discontinue', $this->prescription), [
        'reason' => 'Patient developed adverse reaction to medication',
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Prescription discontinued successfully.',
        ]);

    $this->prescription->refresh();
    expect($this->prescription->discontinued_at)->not->toBeNull()
        ->and($this->prescription->discontinued_by_id)->toBe($this->user->id)
        ->and($this->prescription->discontinuation_reason)->toBe('Patient developed adverse reaction to medication');

    // Check future administrations are cancelled
    $futureAdmin1->refresh();
    $futureAdmin2->refresh();
    expect($futureAdmin1->status)->toBe('cancelled')
        ->and($futureAdmin2->status)->toBe('cancelled');

    // Check given administration is preserved
    $givenAdmin->refresh();
    expect($givenAdmin->status)->toBe('given');
});

it('cannot discontinue already discontinued prescription', function () {
    $this->prescription->update([
        'discontinued_at' => now(),
        'discontinued_by_id' => $this->user->id,
        'discontinuation_reason' => 'Already discontinued',
    ]);

    $response = postJson(route('api.prescriptions.discontinue', $this->prescription), [
        'reason' => 'Trying to discontinue again',
    ]);

    $response->assertForbidden();
});

it('requires reason for discontinuation', function () {
    $response = postJson(route('api.prescriptions.discontinue', $this->prescription), [
        'reason' => '',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

it('requires reason to be at least 10 characters', function () {
    $response = postJson(route('api.prescriptions.discontinue', $this->prescription), [
        'reason' => 'Short',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

it('requires authentication to view prescription schedule', function () {
    auth()->logout();

    $response = getJson(route('api.prescriptions.schedule', $this->prescription));

    $response->assertUnauthorized();
});

it('requires authentication to adjust schedule time', function () {
    $administration = MedicationAdministration::factory()->create([
        'prescription_id' => $this->prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->addHours(2),
        'status' => 'scheduled',
    ]);

    auth()->logout();

    $response = patchJson(route('api.medication-administrations.adjust-time', $administration), [
        'scheduled_time' => now()->addHours(4)->toISOString(),
    ]);

    $response->assertUnauthorized();
});

it('requires authentication to discontinue prescription', function () {
    auth()->logout();

    $response = postJson(route('api.prescriptions.discontinue', $this->prescription), [
        'reason' => 'Test reason for discontinuation',
    ]);

    $response->assertUnauthorized();
});

it('requires permission to adjust schedule time', function () {
    $userWithoutPermission = User::factory()->create();
    actingAs($userWithoutPermission);

    $administration = MedicationAdministration::factory()->create([
        'prescription_id' => $this->prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->addHours(2),
        'status' => 'scheduled',
    ]);

    $response = patchJson(route('api.medication-administrations.adjust-time', $administration), [
        'scheduled_time' => now()->addHours(4)->toISOString(),
    ]);

    $response->assertForbidden();
});

it('requires permission to discontinue prescription', function () {
    $userWithoutPermission = User::factory()->create();
    actingAs($userWithoutPermission);

    $response = postJson(route('api.prescriptions.discontinue', $this->prescription), [
        'reason' => 'Test reason for discontinuation',
    ]);

    $response->assertForbidden();
});

it('returns smart defaults for a prescription', function () {
    $response = getJson(route('api.prescriptions.smart-defaults', $this->prescription));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'prescription' => ['id', 'frequency', 'duration'],
            'defaults' => [
                'day_1',
                'subsequent',
            ],
        ]);

    $defaults = $response->json('defaults');
    expect($defaults)->toBeArray()
        ->and($defaults)->toHaveKey('day_1')
        ->and($defaults)->toHaveKey('subsequent');
});

it('configures schedule pattern and generates administrations', function () {
    $schedulePattern = [
        'day_1' => ['10:30', '20:00'],
        'subsequent' => ['08:00', '20:00'],
    ];

    $response = postJson(route('api.prescriptions.configure-schedule', $this->prescription), [
        'schedule_pattern' => $schedulePattern,
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Medication schedule configured successfully.',
        ]);

    $this->prescription->refresh();
    expect($this->prescription->schedule_pattern)->toBe($schedulePattern);

    // Verify administrations were created
    $administrations = MedicationAdministration::where('prescription_id', $this->prescription->id)->get();
    expect($administrations)->not->toBeEmpty();
});

it('validates schedule pattern structure', function () {
    $response = postJson(route('api.prescriptions.configure-schedule', $this->prescription), [
        'schedule_pattern' => 'invalid',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['schedule_pattern']);
});

it('validates day_1 is required', function () {
    $response = postJson(route('api.prescriptions.configure-schedule', $this->prescription), [
        'schedule_pattern' => [
            'subsequent' => ['08:00', '20:00'],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['schedule_pattern.day_1']);
});

it('validates subsequent is required', function () {
    $response = postJson(route('api.prescriptions.configure-schedule', $this->prescription), [
        'schedule_pattern' => [
            'day_1' => ['08:00', '20:00'],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['schedule_pattern.subsequent']);
});

it('validates schedule pattern time format', function () {
    $response = postJson(route('api.prescriptions.configure-schedule', $this->prescription), [
        'schedule_pattern' => [
            'day_1' => ['invalid-time'],
            'subsequent' => ['08:00'],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['schedule_pattern.day_1.0']);
});

it('validates at least one time per day', function () {
    $response = postJson(route('api.prescriptions.configure-schedule', $this->prescription), [
        'schedule_pattern' => [
            'day_1' => [],
            'subsequent' => ['08:00'],
        ],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['schedule_pattern.day_1']);
});

it('reconfigures existing schedule with new pattern', function () {
    // First configure a schedule
    $originalPattern = [
        'day_1' => ['08:00', '20:00'],
        'subsequent' => ['08:00', '20:00'],
    ];

    $this->prescription->update(['schedule_pattern' => $originalPattern]);

    MedicationAdministration::factory()->create([
        'prescription_id' => $this->prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->addHours(2),
        'status' => 'scheduled',
    ]);

    // Now reconfigure with new pattern
    $newPattern = [
        'day_1' => ['09:00', '21:00'],
        'subsequent' => ['09:00', '21:00'],
    ];

    $response = postJson(route('api.prescriptions.reconfigure-schedule', $this->prescription), [
        'schedule_pattern' => $newPattern,
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Medication schedule reconfigured successfully.',
        ]);

    $this->prescription->refresh();
    expect($this->prescription->schedule_pattern)->toBe($newPattern);
});

it('cannot configure schedule for discontinued prescription', function () {
    $this->prescription->update([
        'discontinued_at' => now(),
        'discontinued_by_id' => $this->user->id,
        'discontinuation_reason' => 'Test discontinuation',
    ]);

    $response = postJson(route('api.prescriptions.configure-schedule', $this->prescription), [
        'schedule_pattern' => [
            'day_1' => ['08:00'],
            'subsequent' => ['08:00'],
        ],
    ]);

    $response->assertForbidden();
});

it('requires permission to configure schedule', function () {
    $userWithoutPermission = User::factory()->create();
    actingAs($userWithoutPermission);

    $response = postJson(route('api.prescriptions.configure-schedule', $this->prescription), [
        'schedule_pattern' => [
            'day_1' => ['08:00'],
            'subsequent' => ['08:00'],
        ],
    ]);

    $response->assertForbidden();
});

it('requires authentication to get smart defaults', function () {
    auth()->logout();

    $response = getJson(route('api.prescriptions.smart-defaults', $this->prescription));

    $response->assertUnauthorized();
});

it('requires authentication to configure schedule', function () {
    auth()->logout();

    $response = postJson(route('api.prescriptions.configure-schedule', $this->prescription), [
        'schedule_pattern' => [
            'day_1' => ['08:00'],
            'subsequent' => ['08:00'],
        ],
    ]);

    $response->assertUnauthorized();
});

// Smart Defaults API Tests

it('suggests correct defaults for BID at 10:30 AM', function () {
    // Create prescription with BID frequency
    $prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'BID',
        'duration' => '5 days',
    ]);

    // Mock current time to 10:30 AM
    $mockTime = now()->setTime(10, 30, 0);
    \Carbon\Carbon::setTestNow($mockTime);

    $response = getJson(route('api.prescriptions.smart-defaults', $prescription));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'prescription' => ['id', 'frequency', 'duration'],
            'defaults' => ['day_1', 'subsequent'],
        ]);

    $defaults = $response->json('defaults');
    
    // Day 1 should be [10:30, 18:00]
    expect($defaults['day_1'])->toBe(['10:30', '18:00'])
        ->and($defaults['subsequent'])->toBe(['06:00', '18:00']);

    \Carbon\Carbon::setTestNow(); // Reset time
});

it('suggests correct defaults for TID at 10:00 AM', function () {
    // Create prescription with TID frequency
    $prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'TID',
        'duration' => '5 days',
    ]);

    // Mock current time to 10:00 AM
    $mockTime = now()->setTime(10, 0, 0);
    \Carbon\Carbon::setTestNow($mockTime);

    $response = getJson(route('api.prescriptions.smart-defaults', $prescription));

    $response->assertSuccessful();

    $defaults = $response->json('defaults');
    
    // Day 1 should be [14:00, 22:00] (next available times after 10:00)
    expect($defaults['day_1'])->toBe(['14:00', '22:00'])
        ->and($defaults['subsequent'])->toBe(['06:00', '14:00', '22:00']);

    \Carbon\Carbon::setTestNow(); // Reset time
});

it('suggests correct defaults for QID', function () {
    // Create prescription with QID frequency
    $prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'QID',
        'duration' => '5 days',
    ]);

    // Mock current time to 08:00 AM
    $mockTime = now()->setTime(8, 0, 0);
    \Carbon\Carbon::setTestNow($mockTime);

    $response = getJson(route('api.prescriptions.smart-defaults', $prescription));

    $response->assertSuccessful();

    $defaults = $response->json('defaults');
    
    // Subsequent should be standard QID times
    expect($defaults['subsequent'])->toBe(['06:00', '12:00', '18:00', '00:00']);

    \Carbon\Carbon::setTestNow(); // Reset time
});

it('calculates Q4H from current time rounded to nearest hour', function () {
    // Create prescription with Q4H frequency
    $prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'Q4H',
        'duration' => '5 days',
    ]);

    // Mock current time to 10:30 AM (should round to 11:00)
    $mockTime = now()->setTime(10, 30, 0);
    \Carbon\Carbon::setTestNow($mockTime);

    $response = getJson(route('api.prescriptions.smart-defaults', $prescription));

    $response->assertSuccessful();

    $defaults = $response->json('defaults');
    
    // Day 1 should start at 11:00 (rounded up from 10:30)
    expect($defaults['day_1'])->toBe(['11:00']);
    
    // Subsequent should have 6 doses every 4 hours starting from 11:00
    expect($defaults['subsequent'])->toHaveCount(6)
        ->and($defaults['subsequent'][0])->toBe('11:00')
        ->and($defaults['subsequent'][1])->toBe('15:00')
        ->and($defaults['subsequent'][2])->toBe('19:00')
        ->and($defaults['subsequent'][3])->toBe('23:00');

    \Carbon\Carbon::setTestNow(); // Reset time
});

it('calculates Q4H from exact hour without rounding', function () {
    // Create prescription with Q4H frequency
    $prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'Q4H',
        'duration' => '5 days',
    ]);

    // Mock current time to exactly 10:00 AM (no rounding needed)
    $mockTime = now()->setTime(10, 0, 0);
    \Carbon\Carbon::setTestNow($mockTime);

    $response = getJson(route('api.prescriptions.smart-defaults', $prescription));

    $response->assertSuccessful();

    $defaults = $response->json('defaults');
    
    // Day 1 should start at 10:00 (no rounding)
    expect($defaults['day_1'])->toBe(['10:00']);
    
    // Subsequent should have 6 doses every 4 hours starting from 10:00
    expect($defaults['subsequent'])->toHaveCount(6)
        ->and($defaults['subsequent'][0])->toBe('10:00')
        ->and($defaults['subsequent'][1])->toBe('14:00')
        ->and($defaults['subsequent'][2])->toBe('18:00')
        ->and($defaults['subsequent'][3])->toBe('22:00');

    \Carbon\Carbon::setTestNow(); // Reset time
});

it('returns empty defaults for PRN medications', function () {
    // Create prescription with PRN frequency
    $prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'PRN',
        'duration' => '5 days',
    ]);

    $response = getJson(route('api.prescriptions.smart-defaults', $prescription));

    $response->assertSuccessful();

    $defaults = $response->json('defaults');
    
    // PRN should return empty arrays
    expect($defaults['day_1'])->toBe([])
        ->and($defaults['subsequent'])->toBe([]);
});

it('suggests correct defaults for Q12H (BID equivalent)', function () {
    // Create prescription with Q12H frequency
    $prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'Q12H',
        'duration' => '5 days',
    ]);

    // Mock current time to 09:00 AM
    $mockTime = now()->setTime(9, 0, 0);
    \Carbon\Carbon::setTestNow($mockTime);

    $response = getJson(route('api.prescriptions.smart-defaults', $prescription));

    $response->assertSuccessful();

    $defaults = $response->json('defaults');
    
    // Day 1 should be [09:00, 18:00]
    expect($defaults['day_1'])->toBe(['09:00', '18:00'])
        ->and($defaults['subsequent'])->toBe(['06:00', '18:00']);

    \Carbon\Carbon::setTestNow(); // Reset time
});

it('suggests correct defaults for Q8H (TID equivalent)', function () {
    // Create prescription with Q8H frequency
    $prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'Q8H',
        'duration' => '5 days',
    ]);

    // Mock current time to 07:00 AM
    $mockTime = now()->setTime(7, 0, 0);
    \Carbon\Carbon::setTestNow($mockTime);

    $response = getJson(route('api.prescriptions.smart-defaults', $prescription));

    $response->assertSuccessful();

    $defaults = $response->json('defaults');
    
    // Day 1 should be next available times after 07:00
    expect($defaults['day_1'])->toBe(['14:00', '22:00'])
        ->and($defaults['subsequent'])->toBe(['06:00', '14:00', '22:00']);

    \Carbon\Carbon::setTestNow(); // Reset time
});

it('suggests correct defaults for Q6H (QID equivalent)', function () {
    // Create prescription with Q6H frequency
    $prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'Q6H',
        'duration' => '5 days',
    ]);

    $response = getJson(route('api.prescriptions.smart-defaults', $prescription));

    $response->assertSuccessful();

    $defaults = $response->json('defaults');
    
    // Q6H should use standard QID times
    expect($defaults['subsequent'])->toBe(['06:00', '12:00', '18:00', '00:00']);
});

it('calculates Q2H from current time rounded to nearest hour', function () {
    // Create prescription with Q2H frequency
    $prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'Q2H',
        'duration' => '5 days',
    ]);

    // Mock current time to 10:45 AM (should round to 11:00)
    $mockTime = now()->setTime(10, 45, 0);
    \Carbon\Carbon::setTestNow($mockTime);

    $response = getJson(route('api.prescriptions.smart-defaults', $prescription));

    $response->assertSuccessful();

    $defaults = $response->json('defaults');
    
    // Day 1 should start at 11:00 (rounded up from 10:45)
    expect($defaults['day_1'])->toBe(['11:00']);
    
    // Subsequent should have 12 doses every 2 hours starting from 11:00
    expect($defaults['subsequent'])->toHaveCount(12)
        ->and($defaults['subsequent'][0])->toBe('11:00')
        ->and($defaults['subsequent'][1])->toBe('13:00')
        ->and($defaults['subsequent'][2])->toBe('15:00')
        ->and($defaults['subsequent'][3])->toBe('17:00');

    \Carbon\Carbon::setTestNow(); // Reset time
});

// Schedule Configuration API Tests

it('authorized user can configure schedule', function () {
    $prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'BID',
        'duration' => '5 days',
    ]);

    $schedulePattern = [
        'day_1' => ['10:30', '18:00'],
        'subsequent' => ['06:00', '18:00'],
    ];

    $response = postJson(route('api.prescriptions.configure-schedule', $prescription), [
        'schedule_pattern' => $schedulePattern,
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Medication schedule configured successfully.',
        ]);

    $prescription->refresh();
    expect($prescription->schedule_pattern)->toBe($schedulePattern);
});

it('configuration creates correct number of medication administration records for BID', function () {
    $prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'BID',
        'duration' => '5 days',
    ]);

    // Delete any auto-generated administrations from observer
    MedicationAdministration::where('prescription_id', $prescription->id)->delete();

    $schedulePattern = [
        'day_1' => ['10:30', '18:00'],
        'subsequent' => ['06:00', '18:00'],
    ];

    $response = postJson(route('api.prescriptions.configure-schedule', $prescription), [
        'schedule_pattern' => $schedulePattern,
    ]);

    $response->assertSuccessful();

    // BID for 5 days = 2 doses on day 1 + 2 doses × 4 remaining days = 10 total doses
    $administrations = MedicationAdministration::where('prescription_id', $prescription->id)->get();
    expect($administrations)->toHaveCount(10);
});

it('configuration creates correct number of medication administration records for TID', function () {
    $prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'TID',
        'duration' => '3 days',
    ]);

    // Delete any auto-generated administrations from observer
    MedicationAdministration::where('prescription_id', $prescription->id)->delete();

    $schedulePattern = [
        'day_1' => ['14:00', '22:00'],
        'subsequent' => ['06:00', '14:00', '22:00'],
    ];

    $response = postJson(route('api.prescriptions.configure-schedule', $prescription), [
        'schedule_pattern' => $schedulePattern,
    ]);

    $response->assertSuccessful();

    // TID for 3 days = 2 doses on day 1 + 3 doses × 2 remaining days = 8 total doses
    $administrations = MedicationAdministration::where('prescription_id', $prescription->id)->get();
    expect($administrations)->toHaveCount(8);
});

it('day 1 pattern is used for first day', function () {
    $prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'BID',
        'duration' => '3 days',
    ]);

    // Delete any auto-generated administrations from observer
    MedicationAdministration::where('prescription_id', $prescription->id)->delete();

    // Mock current time to start of day
    $mockTime = now()->setTime(0, 0, 0);
    \Carbon\Carbon::setTestNow($mockTime);

    $schedulePattern = [
        'day_1' => ['10:30', '18:00'],
        'subsequent' => ['06:00', '18:00'],
    ];

    $response = postJson(route('api.prescriptions.configure-schedule', $prescription), [
        'schedule_pattern' => $schedulePattern,
    ]);

    $response->assertSuccessful();

    // Get day 1 administrations
    $day1Administrations = MedicationAdministration::where('prescription_id', $prescription->id)
        ->whereDate('scheduled_time', $mockTime->toDateString())
        ->orderBy('scheduled_time')
        ->get();

    expect($day1Administrations)->toHaveCount(2)
        ->and($day1Administrations[0]->scheduled_time->format('H:i'))->toBe('10:30')
        ->and($day1Administrations[1]->scheduled_time->format('H:i'))->toBe('18:00');

    \Carbon\Carbon::setTestNow(); // Reset time
});

it('subsequent pattern is used for remaining days', function () {
    $prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'BID',
        'duration' => '3 days',
    ]);

    // Delete any auto-generated administrations from observer
    MedicationAdministration::where('prescription_id', $prescription->id)->delete();

    // Mock current time to start of day
    $mockTime = now()->setTime(0, 0, 0);
    \Carbon\Carbon::setTestNow($mockTime);

    $schedulePattern = [
        'day_1' => ['10:30', '18:00'],
        'subsequent' => ['06:00', '18:00'],
    ];

    $response = postJson(route('api.prescriptions.configure-schedule', $prescription), [
        'schedule_pattern' => $schedulePattern,
    ]);

    $response->assertSuccessful();

    // Get day 2 administrations
    $day2Administrations = MedicationAdministration::where('prescription_id', $prescription->id)
        ->whereDate('scheduled_time', $mockTime->copy()->addDay()->toDateString())
        ->orderBy('scheduled_time')
        ->get();

    expect($day2Administrations)->toHaveCount(2)
        ->and($day2Administrations[0]->scheduled_time->format('H:i'))->toBe('06:00')
        ->and($day2Administrations[1]->scheduled_time->format('H:i'))->toBe('18:00');

    // Get day 3 administrations
    $day3Administrations = MedicationAdministration::where('prescription_id', $prescription->id)
        ->whereDate('scheduled_time', $mockTime->copy()->addDays(2)->toDateString())
        ->orderBy('scheduled_time')
        ->get();

    expect($day3Administrations)->toHaveCount(2)
        ->and($day3Administrations[0]->scheduled_time->format('H:i'))->toBe('06:00')
        ->and($day3Administrations[1]->scheduled_time->format('H:i'))->toBe('18:00');

    \Carbon\Carbon::setTestNow(); // Reset time
});

it('custom day patterns are applied correctly', function () {
    $prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'TID',
        'duration' => '4 days',
    ]);

    // Delete any auto-generated administrations from observer
    MedicationAdministration::where('prescription_id', $prescription->id)->delete();

    // Mock current time to start of day
    $mockTime = now()->setTime(0, 0, 0);
    \Carbon\Carbon::setTestNow($mockTime);

    $schedulePattern = [
        'day_1' => ['10:00', '18:00'],
        'day_2' => ['08:00', '16:00', '23:00'],
        'day_3' => ['07:00', '15:00', '22:00'],
        'subsequent' => ['06:00', '14:00', '22:00'],
    ];

    $response = postJson(route('api.prescriptions.configure-schedule', $prescription), [
        'schedule_pattern' => $schedulePattern,
    ]);

    $response->assertSuccessful();

    // Verify Day 1 uses day_1 pattern
    $day1Administrations = MedicationAdministration::where('prescription_id', $prescription->id)
        ->whereDate('scheduled_time', $mockTime->toDateString())
        ->orderBy('scheduled_time')
        ->get();

    expect($day1Administrations)->toHaveCount(2)
        ->and($day1Administrations[0]->scheduled_time->format('H:i'))->toBe('10:00')
        ->and($day1Administrations[1]->scheduled_time->format('H:i'))->toBe('18:00');

    // Verify Day 2 uses day_2 pattern
    $day2Administrations = MedicationAdministration::where('prescription_id', $prescription->id)
        ->whereDate('scheduled_time', $mockTime->copy()->addDay()->toDateString())
        ->orderBy('scheduled_time')
        ->get();

    expect($day2Administrations)->toHaveCount(3)
        ->and($day2Administrations[0]->scheduled_time->format('H:i'))->toBe('08:00')
        ->and($day2Administrations[1]->scheduled_time->format('H:i'))->toBe('16:00')
        ->and($day2Administrations[2]->scheduled_time->format('H:i'))->toBe('23:00');

    // Verify Day 3 uses day_3 pattern
    $day3Administrations = MedicationAdministration::where('prescription_id', $prescription->id)
        ->whereDate('scheduled_time', $mockTime->copy()->addDays(2)->toDateString())
        ->orderBy('scheduled_time')
        ->get();

    expect($day3Administrations)->toHaveCount(3)
        ->and($day3Administrations[0]->scheduled_time->format('H:i'))->toBe('07:00')
        ->and($day3Administrations[1]->scheduled_time->format('H:i'))->toBe('15:00')
        ->and($day3Administrations[2]->scheduled_time->format('H:i'))->toBe('22:00');

    // Verify Day 4 uses subsequent pattern (no day_4 defined)
    $day4Administrations = MedicationAdministration::where('prescription_id', $prescription->id)
        ->whereDate('scheduled_time', $mockTime->copy()->addDays(3)->toDateString())
        ->orderBy('scheduled_time')
        ->get();

    expect($day4Administrations)->toHaveCount(3)
        ->and($day4Administrations[0]->scheduled_time->format('H:i'))->toBe('06:00')
        ->and($day4Administrations[1]->scheduled_time->format('H:i'))->toBe('14:00')
        ->and($day4Administrations[2]->scheduled_time->format('H:i'))->toBe('22:00');

    \Carbon\Carbon::setTestNow(); // Reset time
});

it('PRN prescriptions cannot be configured', function () {
    $prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'PRN',
        'duration' => '5 days',
    ]);

    $schedulePattern = [
        'day_1' => ['10:00'],
        'subsequent' => ['10:00'],
    ];

    $response = postJson(route('api.prescriptions.configure-schedule', $prescription), [
        'schedule_pattern' => $schedulePattern,
    ]);

    $response->assertForbidden();

    // Verify no administrations were created
    $administrations = MedicationAdministration::where('prescription_id', $prescription->id)->get();
    expect($administrations)->toBeEmpty();
});

it('unauthorized user receives 403 error when configuring schedule', function () {
    $userWithoutPermission = User::factory()->create();
    actingAs($userWithoutPermission);

    $prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'BID',
        'duration' => '5 days',
    ]);

    // Delete any auto-generated administrations from observer
    MedicationAdministration::where('prescription_id', $prescription->id)->delete();

    $schedulePattern = [
        'day_1' => ['10:30', '18:00'],
        'subsequent' => ['06:00', '18:00'],
    ];

    $response = postJson(route('api.prescriptions.configure-schedule', $prescription), [
        'schedule_pattern' => $schedulePattern,
    ]);

    $response->assertForbidden();

    // Verify no new administrations were created by the configure endpoint
    $administrations = MedicationAdministration::where('prescription_id', $prescription->id)->get();
    expect($administrations)->toBeEmpty();
});

// Schedule Reconfiguration API Tests

it('reconfiguration cancels future doses', function () {
    $prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'BID',
        'duration' => '5 days',
    ]);

    // Configure initial schedule
    $originalPattern = [
        'day_1' => ['08:00', '20:00'],
        'subsequent' => ['08:00', '20:00'],
    ];

    $prescription->update(['schedule_pattern' => $originalPattern]);

    // Delete any auto-generated administrations from observer
    MedicationAdministration::where('prescription_id', $prescription->id)->delete();

    // Create future scheduled administrations
    $futureAdmin1 = MedicationAdministration::factory()->create([
        'prescription_id' => $prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->addHours(2),
        'status' => 'scheduled',
    ]);

    $futureAdmin2 = MedicationAdministration::factory()->create([
        'prescription_id' => $prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->addHours(14),
        'status' => 'scheduled',
    ]);

    $futureAdmin3 = MedicationAdministration::factory()->create([
        'prescription_id' => $prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->addDays(1),
        'status' => 'scheduled',
    ]);

    // Reconfigure with new pattern
    $newPattern = [
        'day_1' => ['09:00', '21:00'],
        'subsequent' => ['09:00', '21:00'],
    ];

    $response = postJson(route('api.prescriptions.reconfigure-schedule', $prescription), [
        'schedule_pattern' => $newPattern,
    ]);

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Medication schedule reconfigured successfully.',
        ]);

    // Verify all future scheduled doses are cancelled
    $futureAdmin1->refresh();
    $futureAdmin2->refresh();
    $futureAdmin3->refresh();

    expect($futureAdmin1->status)->toBe('cancelled')
        ->and($futureAdmin2->status)->toBe('cancelled')
        ->and($futureAdmin3->status)->toBe('cancelled');
});

it('reconfiguration preserves given doses', function () {
    $prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'BID',
        'duration' => '5 days',
    ]);

    // Configure initial schedule
    $originalPattern = [
        'day_1' => ['08:00', '20:00'],
        'subsequent' => ['08:00', '20:00'],
    ];

    $prescription->update(['schedule_pattern' => $originalPattern]);

    // Delete any auto-generated administrations from observer
    MedicationAdministration::where('prescription_id', $prescription->id)->delete();

    // Create already given administrations
    $givenAdmin1 = MedicationAdministration::factory()->create([
        'prescription_id' => $prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->subHours(12),
        'status' => 'given',
        'administered_at' => now()->subHours(11),
        'administered_by_id' => $this->user->id,
    ]);

    $givenAdmin2 = MedicationAdministration::factory()->create([
        'prescription_id' => $prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->subHours(2),
        'status' => 'given',
        'administered_at' => now()->subHours(1),
        'administered_by_id' => $this->user->id,
    ]);

    // Create future scheduled administration
    $futureAdmin = MedicationAdministration::factory()->create([
        'prescription_id' => $prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->addHours(2),
        'status' => 'scheduled',
    ]);

    // Reconfigure with new pattern
    $newPattern = [
        'day_1' => ['09:00', '21:00'],
        'subsequent' => ['09:00', '21:00'],
    ];

    $response = postJson(route('api.prescriptions.reconfigure-schedule', $prescription), [
        'schedule_pattern' => $newPattern,
    ]);

    $response->assertSuccessful();

    // Verify given doses are preserved
    $givenAdmin1->refresh();
    $givenAdmin2->refresh();

    expect($givenAdmin1->status)->toBe('given')
        ->and($givenAdmin2->status)->toBe('given')
        ->and($givenAdmin1->administered_at)->not->toBeNull()
        ->and($givenAdmin2->administered_at)->not->toBeNull();

    // Verify future dose is cancelled
    $futureAdmin->refresh();
    expect($futureAdmin->status)->toBe('cancelled');
});

it('reconfiguration creates new schedule with new pattern', function () {
    $prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'BID',
        'duration' => '3 days',
    ]);

    // Configure initial schedule
    $originalPattern = [
        'day_1' => ['08:00', '20:00'],
        'subsequent' => ['08:00', '20:00'],
    ];

    $prescription->update(['schedule_pattern' => $originalPattern]);

    // Delete any auto-generated administrations from observer
    MedicationAdministration::where('prescription_id', $prescription->id)->delete();

    // Create one given administration
    MedicationAdministration::factory()->create([
        'prescription_id' => $prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->subHours(2),
        'status' => 'given',
        'administered_at' => now()->subHours(1),
        'administered_by_id' => $this->user->id,
    ]);

    // Create future scheduled administrations
    MedicationAdministration::factory()->create([
        'prescription_id' => $prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->addHours(2),
        'status' => 'scheduled',
    ]);

    MedicationAdministration::factory()->create([
        'prescription_id' => $prescription->id,
        'patient_admission_id' => $this->admission->id,
        'scheduled_time' => now()->addHours(14),
        'status' => 'scheduled',
    ]);

    // Mock current time to start of day for predictable schedule generation
    $mockTime = now()->setTime(0, 0, 0);
    \Carbon\Carbon::setTestNow($mockTime);

    // Reconfigure with new pattern
    $newPattern = [
        'day_1' => ['09:00', '21:00'],
        'subsequent' => ['09:00', '21:00'],
    ];

    $response = postJson(route('api.prescriptions.reconfigure-schedule', $prescription), [
        'schedule_pattern' => $newPattern,
    ]);

    $response->assertSuccessful();

    // Verify prescription pattern is updated
    $prescription->refresh();
    expect($prescription->schedule_pattern)->toBe($newPattern);

    // Verify new schedule is created with new pattern
    $newAdministrations = MedicationAdministration::where('prescription_id', $prescription->id)
        ->where('status', 'scheduled')
        ->orderBy('scheduled_time')
        ->get();

    // Should have new administrations based on new pattern
    expect($newAdministrations)->not->toBeEmpty();

    // Verify the new administrations use the new times
    $day1Administrations = $newAdministrations->filter(function ($admin) use ($mockTime) {
        return $admin->scheduled_time->isSameDay($mockTime);
    });

    foreach ($day1Administrations as $admin) {
        $time = $admin->scheduled_time->format('H:i');
        expect(in_array($time, ['09:00', '21:00']))->toBeTrue();
    }

    \Carbon\Carbon::setTestNow(); // Reset time
});

it('unauthorized user receives 403 error when reconfiguring schedule', function () {
    $userWithoutPermission = User::factory()->create();
    actingAs($userWithoutPermission);

    $prescription = Prescription::factory()->create([
        'consultation_id' => $this->consultation->id,
        'drug_id' => $this->drug->id,
        'frequency' => 'BID',
        'duration' => '5 days',
    ]);

    // Configure initial schedule
    $originalPattern = [
        'day_1' => ['08:00', '20:00'],
        'subsequent' => ['08:00', '20:00'],
    ];

    $prescription->update(['schedule_pattern' => $originalPattern]);

    // Reconfigure with new pattern
    $newPattern = [
        'day_1' => ['09:00', '21:00'],
        'subsequent' => ['09:00', '21:00'],
    ];

    $response = postJson(route('api.prescriptions.reconfigure-schedule', $prescription), [
        'schedule_pattern' => $newPattern,
    ]);

    $response->assertForbidden();

    // Verify pattern was not updated
    $prescription->refresh();
    expect($prescription->schedule_pattern)->toBe($originalPattern);
});
