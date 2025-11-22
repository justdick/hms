<?php

use App\Models\Charge;
use App\Models\Department;
use App\Models\Drug;
use App\Models\DrugBatch;
use App\Models\MinorProcedure;
use App\Models\MinorProcedureSupply;
use App\Models\PatientCheckin;
use App\Models\User;
use App\Services\DispensingService;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    // Create permission if it doesn't exist
    Permission::firstOrCreate(['name' => 'dispensing.dispense', 'guard_name' => 'web']);

    $this->dispenser = User::factory()->create();
    $this->dispenser->givePermissionTo('dispensing.dispense');

    $this->department = Department::factory()->create([
        'name' => 'Minor Procedures',
        'code' => 'MINPROC',
    ]);

    $this->drug = Drug::factory()->create([
        'name' => 'Gauze Pad',
        'drug_code' => 'GAUZE001',
        'unit_price' => 5.00,
        'form' => 'tablet',
    ]);

    // Create drug batch with stock
    $this->batch = DrugBatch::factory()->create([
        'drug_id' => $this->drug->id,
        'quantity_remaining' => 100,
        'expiry_date' => now()->addYear(),
    ]);
});

it('dispenses minor procedure supply and creates charge', function () {
    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    $procedure = MinorProcedure::factory()->create([
        'patient_checkin_id' => $checkin->id,
    ]);

    $supply = MinorProcedureSupply::factory()->create([
        'minor_procedure_id' => $procedure->id,
        'drug_id' => $this->drug->id,
        'quantity' => 10,
        'dispensed' => false,
    ]);

    actingAs($this->dispenser);

    $service = app(DispensingService::class);
    $service->dispenseMinorProcedureSupply($supply, $this->dispenser);

    // Assert supply is marked as dispensed
    $supply->refresh();
    expect($supply->dispensed)->toBeTrue();
    expect($supply->dispensed_by)->toBe($this->dispenser->id);
    expect($supply->dispensed_at)->not->toBeNull();

    // Assert charge was created
    expect(Charge::count())->toBe(1);

    $charge = Charge::first();
    expect($charge->patient_checkin_id)->toBe($checkin->id);
    expect($charge->service_type)->toBe('pharmacy');
    expect($charge->service_code)->toBe('GAUZE001');
    expect((float) $charge->amount)->toBe(50.00); // 5.00 * 10
    expect($charge->charge_type)->toBe('supply');
    expect($charge->description)->toContain('Minor Procedure Supply');
});

it('deducts stock when dispensing supply', function () {
    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    $procedure = MinorProcedure::factory()->create([
        'patient_checkin_id' => $checkin->id,
    ]);

    $supply = MinorProcedureSupply::factory()->create([
        'minor_procedure_id' => $procedure->id,
        'drug_id' => $this->drug->id,
        'quantity' => 10,
        'dispensed' => false,
    ]);

    actingAs($this->dispenser);

    $initialStock = $this->batch->quantity_remaining;

    $service = app(DispensingService::class);
    $service->dispenseMinorProcedureSupply($supply, $this->dispenser);

    // Assert stock was deducted
    $this->batch->refresh();
    expect($this->batch->quantity_remaining)->toBe($initialStock - 10);
});

it('throws exception when insufficient stock', function () {
    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    $procedure = MinorProcedure::factory()->create([
        'patient_checkin_id' => $checkin->id,
    ]);

    // Request more than available
    $supply = MinorProcedureSupply::factory()->create([
        'minor_procedure_id' => $procedure->id,
        'drug_id' => $this->drug->id,
        'quantity' => 150, // More than the 100 in stock
        'dispensed' => false,
    ]);

    actingAs($this->dispenser);

    $service = app(DispensingService::class);

    expect(fn () => $service->dispenseMinorProcedureSupply($supply, $this->dispenser))
        ->toThrow(\Exception::class, 'Insufficient stock');
});

it('includes supply metadata in charge', function () {
    $checkin = PatientCheckin::factory()->create([
        'department_id' => $this->department->id,
    ]);

    $procedure = MinorProcedure::factory()->create([
        'patient_checkin_id' => $checkin->id,
    ]);

    $supply = MinorProcedureSupply::factory()->create([
        'minor_procedure_id' => $procedure->id,
        'drug_id' => $this->drug->id,
        'quantity' => 5,
        'dispensed' => false,
    ]);

    actingAs($this->dispenser);

    $service = app(DispensingService::class);
    $service->dispenseMinorProcedureSupply($supply, $this->dispenser);

    $charge = Charge::first();
    expect($charge->metadata)->toHaveKey('minor_procedure_supply_id');
    expect($charge->metadata['minor_procedure_supply_id'])->toBe($supply->id);
    expect($charge->metadata['minor_procedure_id'])->toBe($procedure->id);
    expect((float) $charge->metadata['quantity'])->toBe(5.0);
});
