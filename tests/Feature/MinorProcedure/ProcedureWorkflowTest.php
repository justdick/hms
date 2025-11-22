<?php

use App\Events\MinorProcedurePerformed;
use App\Models\Charge;
use App\Models\Department;
use App\Models\Diagnosis;
use App\Models\Drug;
use App\Models\MinorProcedure;
use App\Models\MinorProcedureType;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->department = Department::factory()->create([
        'name' => 'Minor Procedures',
        'code' => 'MINPROC',
    ]);

    $this->nurse = User::factory()->create();
    $this->nurse->givePermissionTo('minor-procedures.perform');
    $this->nurse->departments()->attach($this->department->id);

    $this->procedureType = MinorProcedureType::factory()->create([
        'name' => 'Wound Dressing',
        'price' => 50.00,
    ]);
});

it('creates procedure successfully with all data', function () {
    $patient = Patient::factory()->create();
    $checkin = PatientCheckin::factory()->create([
        'patient_id' => $patient->id,
        'department_id' => $this->department->id,
        'status' => 'checked_in',
    ]);

    $diagnoses = Diagnosis::factory()->count(2)->create();
    $drug = Drug::factory()->create();

    $response = actingAs($this->nurse)->post('/minor-procedures', [
        'patient_checkin_id' => $checkin->id,
        'minor_procedure_type_id' => $this->procedureType->id,
        'procedure_notes' => 'Patient presented with minor laceration on left forearm. Wound cleaned and dressed.',
        'diagnoses' => $diagnoses->pluck('id')->toArray(),
        'supplies' => [
            [
                'drug_id' => $drug->id,
                'quantity' => 5,
            ],
        ],
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(MinorProcedure::count())->toBe(1);

    $procedure = MinorProcedure::first();
    expect($procedure->patient_checkin_id)->toBe($checkin->id);
    expect($procedure->nurse_id)->toBe($this->nurse->id);
    expect($procedure->minor_procedure_type_id)->toBe($this->procedureType->id);
    expect($procedure->status)->toBe('completed');
    expect($procedure->performed_at)->not->toBeNull();

    // Check diagnoses attached
    expect($procedure->diagnoses)->toHaveCount(2);

    // Check supplies created
    expect($procedure->supplies)->toHaveCount(1);
    expect($procedure->supplies->first()->drug_id)->toBe($drug->id);
    expect((float) $procedure->supplies->first()->quantity)->toBe(5.0);

    // Check check-in status updated
    $checkin->refresh();
    expect($checkin->status)->toBe('completed');
});

it('creates procedure without diagnoses', function () {
    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    $response = actingAs($this->nurse)->post('/minor-procedures', [
        'patient_checkin_id' => $checkin->id,
        'minor_procedure_type_id' => $this->procedureType->id,
        'procedure_notes' => 'Simple procedure without specific diagnosis.',
    ]);

    $response->assertRedirect();

    $procedure = MinorProcedure::first();
    expect($procedure->diagnoses)->toHaveCount(0);
});

it('creates procedure without supplies', function () {
    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    $response = actingAs($this->nurse)->post('/minor-procedures', [
        'patient_checkin_id' => $checkin->id,
        'minor_procedure_type_id' => $this->procedureType->id,
        'procedure_notes' => 'Procedure completed without additional supplies.',
    ]);

    $response->assertRedirect();

    $procedure = MinorProcedure::first();
    expect($procedure->supplies)->toHaveCount(0);
});

it('dispatches event when procedure is created', function () {
    Event::fake([MinorProcedurePerformed::class]);

    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    actingAs($this->nurse)->post('/minor-procedures', [
        'patient_checkin_id' => $checkin->id,
        'minor_procedure_type_id' => $this->procedureType->id,
        'procedure_notes' => 'Test procedure for event dispatch.',
    ]);

    Event::assertDispatched(MinorProcedurePerformed::class, function ($event) {
        return $event->minorProcedure instanceof MinorProcedure;
    });
});

it('creates charge when procedure is performed', function () {
    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    actingAs($this->nurse)->post('/minor-procedures', [
        'patient_checkin_id' => $checkin->id,
        'minor_procedure_type_id' => $this->procedureType->id,
        'procedure_notes' => 'Test procedure for charge creation.',
    ]);

    // Should have procedure charge (consultation fee is separate, created at check-in)
    $procedureCharge = Charge::where('service_type', 'minor_procedure')->first();

    expect($procedureCharge)->not->toBeNull();
    expect($procedureCharge->patient_checkin_id)->toBe($checkin->id);
    expect($procedureCharge->service_type)->toBe('minor_procedure');
    expect((float) $procedureCharge->amount)->toBe(50.00);
    expect($procedureCharge->status)->toBe('pending');
});

it('validates required fields', function () {
    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    $response = actingAs($this->nurse)->post('/minor-procedures', [
        'patient_checkin_id' => $checkin->id,
        // Missing required fields
    ]);

    $response->assertSessionHasErrors(['minor_procedure_type_id']);
});

it('validates procedure notes minimum length', function () {
    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    $response = actingAs($this->nurse)->post('/minor-procedures', [
        'patient_checkin_id' => $checkin->id,
        'minor_procedure_type_id' => $this->procedureType->id,
        'procedure_notes' => 'X', // Less than 2 characters
    ]);

    $response->assertSessionHasErrors(['procedure_notes']);
});

it('validates patient checkin exists', function () {
    $response = actingAs($this->nurse)->post('/minor-procedures', [
        'patient_checkin_id' => 99999, // Non-existent
        'minor_procedure_type_id' => $this->procedureType->id,
        'procedure_notes' => 'Valid notes here.',
    ]);

    $response->assertSessionHasErrors(['patient_checkin_id']);
});

it('validates procedure type exists', function () {
    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    $response = actingAs($this->nurse)->post('/minor-procedures', [
        'patient_checkin_id' => $checkin->id,
        'minor_procedure_type_id' => 99999, // Non-existent
        'procedure_notes' => 'Valid notes here.',
    ]);

    $response->assertSessionHasErrors(['minor_procedure_type_id']);
});

it('validates diagnoses exist', function () {
    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    $response = actingAs($this->nurse)->post('/minor-procedures', [
        'patient_checkin_id' => $checkin->id,
        'minor_procedure_type_id' => $this->procedureType->id,
        'procedure_notes' => 'Valid notes here.',
        'diagnoses' => [99999], // Non-existent
    ]);

    $response->assertSessionHasErrors(['diagnoses.0']);
});

it('validates supply drug exists', function () {
    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    $response = actingAs($this->nurse)->post('/minor-procedures', [
        'patient_checkin_id' => $checkin->id,
        'minor_procedure_type_id' => $this->procedureType->id,
        'procedure_notes' => 'Valid notes here.',
        'supplies' => [
            [
                'drug_id' => 99999, // Non-existent
                'quantity' => 5,
            ],
        ],
    ]);

    $response->assertSessionHasErrors(['supplies.0.drug_id']);
});

it('validates supply quantity is positive', function () {
    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);
    $drug = Drug::factory()->create();

    $response = actingAs($this->nurse)->post('/minor-procedures', [
        'patient_checkin_id' => $checkin->id,
        'minor_procedure_type_id' => $this->procedureType->id,
        'procedure_notes' => 'Valid notes here.',
        'supplies' => [
            [
                'drug_id' => $drug->id,
                'quantity' => 0, // Invalid
            ],
        ],
    ]);

    $response->assertSessionHasErrors(['supplies.0.quantity']);
});

it('denies unauthorized user', function () {
    $unauthorizedUser = User::factory()->create();
    // No permissions assigned

    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    $response = actingAs($unauthorizedUser)->post('/minor-procedures', [
        'patient_checkin_id' => $checkin->id,
        'minor_procedure_type_id' => $this->procedureType->id,
        'procedure_notes' => 'Attempting unauthorized procedure.',
    ]);

    $response->assertForbidden();
});

it('allows admin to perform procedures', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    $response = actingAs($admin)->post('/minor-procedures', [
        'patient_checkin_id' => $checkin->id,
        'minor_procedure_type_id' => $this->procedureType->id,
        'procedure_notes' => 'Admin performing procedure.',
    ]);

    $response->assertRedirect();
    expect(MinorProcedure::count())->toBe(1);
});

it('creates multiple supplies for single procedure', function () {
    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    $drug1 = Drug::factory()->create(['name' => 'Gauze']);
    $drug2 = Drug::factory()->create(['name' => 'Bandage']);
    $drug3 = Drug::factory()->create(['name' => 'Antiseptic']);

    actingAs($this->nurse)->post('/minor-procedures', [
        'patient_checkin_id' => $checkin->id,
        'minor_procedure_type_id' => $this->procedureType->id,
        'procedure_notes' => 'Procedure requiring multiple supplies.',
        'supplies' => [
            ['drug_id' => $drug1->id, 'quantity' => 5],
            ['drug_id' => $drug2->id, 'quantity' => 2],
            ['drug_id' => $drug3->id, 'quantity' => 1],
        ],
    ]);

    $procedure = MinorProcedure::first();
    expect($procedure->supplies)->toHaveCount(3);

    $supplies = $procedure->supplies;
    expect($supplies->where('drug_id', $drug1->id)->first()->quantity)->toBe('5.00');
    expect($supplies->where('drug_id', $drug2->id)->first()->quantity)->toBe('2.00');
    expect($supplies->where('drug_id', $drug3->id)->first()->quantity)->toBe('1.00');
});

it('handles decimal quantities for supplies', function () {
    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    $drug = Drug::factory()->create();

    actingAs($this->nurse)->post('/minor-procedures', [
        'patient_checkin_id' => $checkin->id,
        'minor_procedure_type_id' => $this->procedureType->id,
        'procedure_notes' => 'Procedure with decimal quantity.',
        'supplies' => [
            ['drug_id' => $drug->id, 'quantity' => 2.5],
        ],
    ]);

    $procedure = MinorProcedure::first();
    expect($procedure->supplies->first()->quantity)->toBe('2.50');
});

it('sets performed_at timestamp when creating procedure', function () {
    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    $beforeTime = now()->subSecond();

    actingAs($this->nurse)->post('/minor-procedures', [
        'patient_checkin_id' => $checkin->id,
        'minor_procedure_type_id' => $this->procedureType->id,
        'procedure_notes' => 'Testing timestamp.',
    ]);

    $afterTime = now()->addSecond();

    $procedure = MinorProcedure::first();
    expect($procedure->performed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($procedure->performed_at->between($beforeTime, $afterTime))->toBeTrue();
});

it('does not create charge if procedure type has zero price', function () {
    $freeType = MinorProcedureType::factory()->create([
        'name' => 'Free Consultation',
        'code' => 'MINP-FREE',
        'price' => 0.00,
    ]);

    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    actingAs($this->nurse)->post('/minor-procedures', [
        'patient_checkin_id' => $checkin->id,
        'minor_procedure_type_id' => $freeType->id,
        'procedure_notes' => 'Free procedure, no charge expected.',
    ]);

    $procedureCharges = Charge::where('service_type', 'minor_procedure')->count();
    expect($procedureCharges)->toBe(0);
});
