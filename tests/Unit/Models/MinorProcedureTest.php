<?php

use App\Models\Diagnosis;
use App\Models\MinorProcedure;
use App\Models\MinorProcedureSupply;
use App\Models\MinorProcedureType;
use App\Models\PatientCheckin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('belongs to patient checkin', function () {
    $checkin = PatientCheckin::factory()->create();
    $procedure = MinorProcedure::factory()->create([
        'patient_checkin_id' => $checkin->id,
    ]);

    expect($procedure->patientCheckin)->toBeInstanceOf(PatientCheckin::class);
    expect($procedure->patientCheckin->id)->toBe($checkin->id);
});

it('belongs to nurse', function () {
    $nurse = User::factory()->create();
    $procedure = MinorProcedure::factory()->create([
        'nurse_id' => $nurse->id,
    ]);

    expect($procedure->nurse)->toBeInstanceOf(User::class);
    expect($procedure->nurse->id)->toBe($nurse->id);
});

it('belongs to minor procedure type', function () {
    $type = MinorProcedureType::factory()->create();
    $procedure = MinorProcedure::factory()->create([
        'minor_procedure_type_id' => $type->id,
    ]);

    expect($procedure->procedureType)->toBeInstanceOf(MinorProcedureType::class);
    expect($procedure->procedureType->id)->toBe($type->id);
});

it('has many diagnoses', function () {
    $procedure = MinorProcedure::factory()->create();
    $diagnoses = Diagnosis::factory()->count(3)->create();

    $procedure->diagnoses()->attach($diagnoses->pluck('id'));

    expect($procedure->diagnoses)->toHaveCount(3);
    expect($procedure->diagnoses->first())->toBeInstanceOf(Diagnosis::class);
});

it('has many supplies', function () {
    $procedure = MinorProcedure::factory()->create();
    $supplies = MinorProcedureSupply::factory()->count(3)->create([
        'minor_procedure_id' => $procedure->id,
    ]);

    expect($procedure->supplies)->toHaveCount(3);
    expect($procedure->supplies->first())->toBeInstanceOf(MinorProcedureSupply::class);
});

it('casts performed_at as datetime', function () {
    $procedure = MinorProcedure::factory()->create([
        'performed_at' => '2025-01-21 10:30:00',
    ]);

    expect($procedure->performed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($procedure->performed_at->format('Y-m-d H:i:s'))->toBe('2025-01-21 10:30:00');
});

it('casts status correctly', function () {
    $procedure = MinorProcedure::factory()->create([
        'status' => 'completed',
    ]);

    expect($procedure->status)->toBe('completed');
    expect($procedure->status)->toBeString();
});

it('factory creates valid minor procedure', function () {
    $procedure = MinorProcedure::factory()->create();

    expect($procedure)->toBeInstanceOf(MinorProcedure::class);
    expect($procedure->patient_checkin_id)->not->toBeNull();
    expect($procedure->nurse_id)->not->toBeNull();
    expect($procedure->minor_procedure_type_id)->not->toBeNull();
    expect($procedure->procedure_notes)->not->toBeNull();
    expect($procedure->status)->toBeIn(['in_progress', 'completed']);
});

it('factory creates procedure with relationships', function () {
    $procedure = MinorProcedure::factory()->create();

    expect($procedure->patientCheckin)->toBeInstanceOf(PatientCheckin::class);
    expect($procedure->nurse)->toBeInstanceOf(User::class);
    expect($procedure->procedureType)->toBeInstanceOf(MinorProcedureType::class);
});
