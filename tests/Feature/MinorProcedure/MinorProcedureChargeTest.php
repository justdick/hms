<?php

use App\Events\MinorProcedurePerformed;
use App\Models\BillingConfiguration;
use App\Models\Charge;
use App\Models\Department;
use App\Models\DepartmentBilling;
use App\Models\MinorProcedure;
use App\Models\PatientCheckin;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Create permission if it doesn't exist
    Permission::firstOrCreate(['name' => 'minor-procedures.perform', 'guard_name' => 'web']);

    $this->nurse = User::factory()->create();
    $this->nurse->givePermissionTo('minor-procedures.perform');

    // Create Minor Procedures department with billing
    $this->department = Department::factory()->create([
        'name' => 'Minor Procedures',
        'code' => 'MINPROC',
    ]);

    $this->departmentBilling = DepartmentBilling::create([
        'department_id' => $this->department->id,
        'department_name' => $this->department->name,
        'department_code' => $this->department->code,
        'consultation_fee' => 50.00,
        'requires_vitals' => false,
    ]);

    // Create procedure type
    $this->procedureType = \App\Models\MinorProcedureType::create([
        'name' => 'Wound Dressing',
        'code' => 'WD001',
        'category' => 'wound_care',
        'description' => 'Cleaning and dressing of wounds',
        'price' => 100.00,
        'is_active' => true,
    ]);

    // Enable auto billing
    BillingConfiguration::updateOrCreate(
        ['key' => 'auto_billing_enabled'],
        ['value' => true]
    );
});

it('creates charge when minor procedure is performed', function () {
    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    $procedure = MinorProcedure::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'nurse_id' => $this->nurse->id,
        'minor_procedure_type_id' => $this->procedureType->id,
    ]);

    // Load the relationships
    $procedure->load(['patientCheckin', 'procedureType']);

    // Manually call the listener (since event auto-discovery might not work in tests)
    $listener = new \App\Listeners\CreateMinorProcedureCharge;
    $listener->handle(new MinorProcedurePerformed($procedure));

    // Assert charge was created
    expect(Charge::count())->toBe(1);

    $charge = Charge::first();
    expect($charge->patient_checkin_id)->toBe($checkin->id);
    expect($charge->service_type)->toBe('minor_procedure');
    expect($charge->service_code)->toBe('WD001');
    expect((float) $charge->amount)->toBe(100.00);
    expect($charge->charge_type)->toBe('minor_procedure');
    expect($charge->status)->toBe('pending');
});

it('does not create charge when auto billing is disabled', function () {
    BillingConfiguration::updateOrCreate(
        ['key' => 'auto_billing_enabled'],
        ['value' => false]
    );

    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    $procedure = MinorProcedure::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'nurse_id' => $this->nurse->id,
    ]);

    event(new MinorProcedurePerformed($procedure));

    expect(Charge::count())->toBe(0);
});

it('does not create procedure charge when procedure price is zero', function () {
    // Create a procedure type with zero price
    $standardProcedure = \App\Models\MinorProcedureType::create([
        'name' => 'Standard Procedure',
        'code' => 'SP001',
        'category' => 'general',
        'price' => 0.00,
        'is_active' => true,
    ]);

    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    $procedure = MinorProcedure::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'nurse_id' => $this->nurse->id,
        'minor_procedure_type_id' => $standardProcedure->id,
    ]);

    $procedure->load(['patientCheckin.department', 'procedureType']);

    $listener = new \App\Listeners\CreateMinorProcedureCharge;
    $listener->handle(new MinorProcedurePerformed($procedure));

    // Should not create procedure-specific charge (consultation fee charged at check-in separately)
    expect(Charge::count())->toBe(0);
});

it('includes procedure metadata in charge', function () {
    $suturingType = \App\Models\MinorProcedureType::create([
        'name' => 'Suturing',
        'code' => 'SUT001',
        'category' => 'wound_care',
        'price' => 120.00,
        'is_active' => true,
    ]);

    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    $procedure = MinorProcedure::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'nurse_id' => $this->nurse->id,
        'minor_procedure_type_id' => $suturingType->id,
    ]);

    // Load the relationships
    $procedure->load(['patientCheckin', 'procedureType']);

    // Manually call the listener
    $listener = new \App\Listeners\CreateMinorProcedureCharge;
    $listener->handle(new MinorProcedurePerformed($procedure));

    $charge = Charge::first();
    expect($charge->metadata)->toHaveKey('minor_procedure_id');
    expect($charge->metadata['minor_procedure_id'])->toBe($procedure->id);
    expect($charge->metadata['minor_procedure_type_id'])->toBe($suturingType->id);
    expect($charge->metadata['procedure_type_code'])->toBe('SUT001');
    expect($charge->metadata['procedure_type_name'])->toBe('Suturing');
});
