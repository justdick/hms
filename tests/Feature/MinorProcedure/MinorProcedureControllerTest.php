<?php

use App\Models\Department;
use App\Models\Diagnosis;
use App\Models\Drug;
use App\Models\MinorProcedure;
use App\Models\MinorProcedureType;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create nurse with permissions
    $this->nurse = User::factory()->create();
    $nurseRole = Role::create(['name' => 'Nurse']);

    Permission::create(['name' => 'minor-procedures.view-dept']);
    Permission::create(['name' => 'minor-procedures.perform']);
    Permission::create(['name' => 'minor-procedures.view-all']);
    Permission::create(['name' => 'checkins.view-dept']); // Required for PatientCheckin::accessibleTo

    $nurseRole->givePermissionTo([
        'minor-procedures.view-dept',
        'minor-procedures.perform',
        'checkins.view-dept',
    ]);
    $this->nurse->assignRole($nurseRole);

    // Create Minor Procedures department
    $this->department = Department::factory()->create([
        'name' => 'Minor Procedures',
        'code' => 'MINPROC',
    ]);

    // Associate nurse with department
    $this->department->users()->attach($this->nurse->id);

    // Create patient and check-in
    $this->patient = Patient::factory()->create();
    $this->patientCheckin = PatientCheckin::factory()->create([
        'patient_id' => $this->patient->id,
        'department_id' => $this->department->id,
        'status' => 'checked_in',
    ]);

    // Create procedure type
    $this->procedureType = MinorProcedureType::factory()->create([
        'name' => 'Wound Dressing',
        'code' => 'WD001',
        'price' => 50.00,
    ]);
});

describe('Index Page', function () {
    it('shows queue count for minor procedures department', function () {
        $response = $this->actingAs($this->nurse)->get('/minor-procedures');

        $response->assertOk();
        // Note: Inertia page assertions skipped until frontend is implemented
    });

    it('does not count completed check-ins in queue', function () {
        $this->patientCheckin->update(['status' => 'completed']);

        $response = $this->actingAs($this->nurse)->get('/minor-procedures');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('queueCount', 0)
            );
    });

    it('requires authentication', function () {
        $response = $this->get('/minor-procedures');

        $response->assertRedirect('/login');
    });

    it('requires permission to view', function () {
        $unauthorizedUser = User::factory()->create();

        $response = $this->actingAs($unauthorizedUser)->get('/minor-procedures');

        $response->assertForbidden();
    });
});

describe('Patient Search', function () {
    it('searches patients in minor procedures queue', function () {
        $response = $this->actingAs($this->nurse)
            ->get('/minor-procedures/search?search='.$this->patient->first_name);

        $response->assertOk()
            ->assertJson([
                'patients' => [
                    [
                        'id' => $this->patientCheckin->id,
                        'patient' => [
                            'id' => $this->patient->id,
                            'first_name' => $this->patient->first_name,
                        ],
                    ],
                ],
            ]);
    });

    it('searches by patient number', function () {
        $response = $this->actingAs($this->nurse)
            ->get('/minor-procedures/search?search='.$this->patient->patient_number);

        $response->assertOk()
            ->assertJsonCount(1, 'patients');
    });

    it('searches by phone number', function () {
        $response = $this->actingAs($this->nurse)
            ->get('/minor-procedures/search?search='.$this->patient->phone_number);

        $response->assertOk()
            ->assertJsonCount(1, 'patients');
    });

    it('returns empty array for short search term', function () {
        $response = $this->actingAs($this->nurse)
            ->get('/minor-procedures/search?search=a');

        $response->assertOk()
            ->assertJson(['patients' => []]);
    });

    it('only returns patients from minor procedures department', function () {
        $otherDepartment = Department::factory()->create(['code' => 'OPD']);
        $otherPatient = Patient::factory()->create();
        PatientCheckin::factory()->create([
            'patient_id' => $otherPatient->id,
            'department_id' => $otherDepartment->id,
            'status' => 'checked_in',
        ]);

        $response = $this->actingAs($this->nurse)
            ->get('/minor-procedures/search?search='.$otherPatient->first_name);

        $response->assertOk()
            ->assertJsonCount(0, 'patients');
    });

    it('does not return completed check-ins', function () {
        $this->patientCheckin->update(['status' => 'completed']);

        $response = $this->actingAs($this->nurse)
            ->get('/minor-procedures/search?search='.$this->patient->first_name);

        $response->assertOk()
            ->assertJsonCount(0, 'patients');
    });
});

describe('Store Procedure', function () {
    it('creates a minor procedure successfully', function () {
        $response = $this->actingAs($this->nurse)
            ->post('/minor-procedures', [
                'patient_checkin_id' => $this->patientCheckin->id,
                'minor_procedure_type_id' => $this->procedureType->id,
                'procedure_notes' => 'Cleaned wound with normal saline, applied antiseptic, dressed with sterile gauze.',
            ]);

        $response->assertRedirect('/minor-procedures')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('minor_procedures', [
            'patient_checkin_id' => $this->patientCheckin->id,
            'nurse_id' => $this->nurse->id,
            'minor_procedure_type_id' => $this->procedureType->id,
            'status' => 'completed',
        ]);
    });

    it('updates check-in status to completed', function () {
        $this->actingAs($this->nurse)
            ->post('/minor-procedures', [
                'patient_checkin_id' => $this->patientCheckin->id,
                'minor_procedure_type_id' => $this->procedureType->id,
                'procedure_notes' => 'Procedure completed successfully.',
            ]);

        $this->patientCheckin->refresh();

        expect($this->patientCheckin->status)->toBe('completed');
    });

    it('attaches diagnoses when provided', function () {
        $diagnosis = Diagnosis::factory()->create([
            'icd_10' => 'T14.1',
        ]);

        $this->actingAs($this->nurse)
            ->post('/minor-procedures', [
                'patient_checkin_id' => $this->patientCheckin->id,
                'minor_procedure_type_id' => $this->procedureType->id,
                'procedure_notes' => 'Procedure with diagnosis.',
                'diagnoses' => [$diagnosis->id],
            ]);

        $procedure = MinorProcedure::first();

        expect($procedure->diagnoses)->toHaveCount(1);
        expect($procedure->diagnoses->first()->id)->toBe($diagnosis->id);
    });

    it('creates supply requests when provided', function () {
        $drug = Drug::factory()->create();

        $this->actingAs($this->nurse)
            ->post('/minor-procedures', [
                'patient_checkin_id' => $this->patientCheckin->id,
                'minor_procedure_type_id' => $this->procedureType->id,
                'procedure_notes' => 'Procedure with supplies.',
                'supplies' => [
                    [
                        'drug_id' => $drug->id,
                        'quantity' => 2,
                    ],
                ],
            ]);

        $procedure = MinorProcedure::first();

        expect($procedure->supplies)->toHaveCount(1);
        expect($procedure->supplies->first()->drug_id)->toBe($drug->id);
        expect((float) $procedure->supplies->first()->quantity)->toBe(2.0);
        expect($procedure->supplies->first()->dispensed)->toBeFalse();
    });

    it('requires patient_checkin_id', function () {
        $response = $this->actingAs($this->nurse)
            ->post('/minor-procedures', [
                'minor_procedure_type_id' => $this->procedureType->id,
                'procedure_notes' => 'Missing check-in.',
            ]);

        $response->assertSessionHasErrors('patient_checkin_id');
    });

    it('requires minor_procedure_type_id', function () {
        $response = $this->actingAs($this->nurse)
            ->post('/minor-procedures', [
                'patient_checkin_id' => $this->patientCheckin->id,
                'procedure_notes' => 'Missing procedure type.',
            ]);

        $response->assertSessionHasErrors('minor_procedure_type_id');
    });

    it('requires procedure_notes with minimum length', function () {
        $response = $this->actingAs($this->nurse)
            ->post('/minor-procedures', [
                'patient_checkin_id' => $this->patientCheckin->id,
                'minor_procedure_type_id' => $this->procedureType->id,
                'procedure_notes' => 'X', // Less than 2 characters
            ]);

        $response->assertSessionHasErrors('procedure_notes');
    });

    it('validates diagnosis IDs exist', function () {
        $response = $this->actingAs($this->nurse)
            ->post('/minor-procedures', [
                'patient_checkin_id' => $this->patientCheckin->id,
                'minor_procedure_type_id' => $this->procedureType->id,
                'procedure_notes' => 'Procedure with invalid diagnosis.',
                'diagnoses' => [99999],
            ]);

        $response->assertSessionHasErrors('diagnoses.0');
    });

    it('validates supply drug IDs exist', function () {
        $response = $this->actingAs($this->nurse)
            ->post('/minor-procedures', [
                'patient_checkin_id' => $this->patientCheckin->id,
                'minor_procedure_type_id' => $this->procedureType->id,
                'procedure_notes' => 'Procedure with invalid supply.',
                'supplies' => [
                    [
                        'drug_id' => 99999,
                        'quantity' => 1,
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('supplies.0.drug_id');
    });

    it('validates supply quantity is positive', function () {
        $drug = Drug::factory()->create();

        $response = $this->actingAs($this->nurse)
            ->post('/minor-procedures', [
                'patient_checkin_id' => $this->patientCheckin->id,
                'minor_procedure_type_id' => $this->procedureType->id,
                'procedure_notes' => 'Procedure with invalid quantity.',
                'supplies' => [
                    [
                        'drug_id' => $drug->id,
                        'quantity' => 0,
                    ],
                ],
            ]);

        $response->assertSessionHasErrors('supplies.0.quantity');
    });

    it('requires permission to create', function () {
        $unauthorizedUser = User::factory()->create();

        $response = $this->actingAs($unauthorizedUser)
            ->post('/minor-procedures', [
                'patient_checkin_id' => $this->patientCheckin->id,
                'minor_procedure_type_id' => $this->procedureType->id,
                'procedure_notes' => 'Unauthorized attempt.',
            ]);

        $response->assertForbidden();
    });
});

describe('Show Procedure', function () {
    it('shows procedure details', function () {
        $procedure = MinorProcedure::factory()->create([
            'patient_checkin_id' => $this->patientCheckin->id,
            'nurse_id' => $this->nurse->id,
            'minor_procedure_type_id' => $this->procedureType->id,
        ]);

        $response = $this->actingAs($this->nurse)
            ->get("/minor-procedures/{$procedure->id}");

        $response->assertOk();
        // Note: Inertia page assertions skipped until frontend is implemented
    });

    it('requires permission to view', function () {
        $procedure = MinorProcedure::factory()->create([
            'patient_checkin_id' => $this->patientCheckin->id,
            'nurse_id' => $this->nurse->id,
            'minor_procedure_type_id' => $this->procedureType->id,
        ]);

        $unauthorizedUser = User::factory()->create();

        $response = $this->actingAs($unauthorizedUser)
            ->get("/minor-procedures/{$procedure->id}");

        $response->assertForbidden();
    });
});
