<?php

use App\Models\Department;
use App\Models\MinorProcedure;
use App\Models\PatientCheckin;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

// viewAny tests
it('allows users with view-dept permission to view any procedures', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('minor-procedures.view-dept');

    expect($user->can('viewAny', MinorProcedure::class))->toBeTrue();
});

it('allows users with view-all permission to view any procedures', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('minor-procedures.view-all');

    expect($user->can('viewAny', MinorProcedure::class))->toBeTrue();
});

it('allows admin to view any procedures', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    expect($admin->can('viewAny', MinorProcedure::class))->toBeTrue();
});

it('denies users without permissions to view any procedures', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', MinorProcedure::class))->toBeFalse();
});

// view tests
it('allows admin to view specific procedure', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $procedure = MinorProcedure::factory()->create();

    expect($admin->can('view', $procedure))->toBeTrue();
});

it('allows user with view-all to view specific procedure', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('minor-procedures.view-all');

    $procedure = MinorProcedure::factory()->create();

    expect($user->can('view', $procedure))->toBeTrue();
});

it('allows user with view-dept to view procedure in their department', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('minor-procedures.view-dept');
    $user->departments()->attach($department->id);

    $checkin = PatientCheckin::factory()->create(['department_id' => $department->id]);
    $procedure = MinorProcedure::factory()->create(['patient_checkin_id' => $checkin->id]);

    expect($user->can('view', $procedure))->toBeTrue();
});

it('denies user with view-dept to view procedure in other department', function () {
    $department1 = Department::factory()->create();
    $department2 = Department::factory()->create();

    $user = User::factory()->create();
    $user->givePermissionTo('minor-procedures.view-dept');
    $user->departments()->attach($department1->id);

    $checkin = PatientCheckin::factory()->create(['department_id' => $department2->id]);
    $procedure = MinorProcedure::factory()->create(['patient_checkin_id' => $checkin->id]);

    expect($user->can('view', $procedure))->toBeFalse();
});

// create tests
it('allows user with perform permission to create procedures', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('minor-procedures.perform');

    expect($user->can('create', MinorProcedure::class))->toBeTrue();
});

it('allows admin to create procedures', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    expect($admin->can('create', MinorProcedure::class))->toBeTrue();
});

it('denies user without perform permission to create procedures', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('minor-procedures.view-dept');

    expect($user->can('create', MinorProcedure::class))->toBeFalse();
});

// update tests
it('allows admin to update any procedure', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $procedure = MinorProcedure::factory()->create(['status' => 'in_progress']);

    expect($admin->can('update', $procedure))->toBeTrue();
});

it('allows admin to update completed procedure', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $procedure = MinorProcedure::factory()->create(['status' => 'completed']);

    expect($admin->can('update', $procedure))->toBeTrue();
});

it('denies updating completed procedures for non-admin', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('minor-procedures.perform');
    $user->departments()->attach($department->id);

    $checkin = PatientCheckin::factory()->create(['department_id' => $department->id]);
    $procedure = MinorProcedure::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'status' => 'completed',
    ]);

    expect($user->can('update', $procedure))->toBeFalse();
});

it('allows user with perform permission to update in-progress procedure in their department', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('minor-procedures.perform');
    $user->departments()->attach($department->id);

    $checkin = PatientCheckin::factory()->create(['department_id' => $department->id]);
    $procedure = MinorProcedure::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'status' => 'in_progress',
    ]);

    expect($user->can('update', $procedure))->toBeTrue();
});

it('denies user to update procedure in other department', function () {
    $department1 = Department::factory()->create();
    $department2 = Department::factory()->create();

    $user = User::factory()->create();
    $user->givePermissionTo('minor-procedures.perform');
    $user->departments()->attach($department1->id);

    $checkin = PatientCheckin::factory()->create(['department_id' => $department2->id]);
    $procedure = MinorProcedure::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'status' => 'in_progress',
    ]);

    expect($user->can('update', $procedure))->toBeFalse();
});

it('denies user without perform permission to update procedure', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('minor-procedures.view-dept');
    $user->departments()->attach($department->id);

    $checkin = PatientCheckin::factory()->create(['department_id' => $department->id]);
    $procedure = MinorProcedure::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'status' => 'in_progress',
    ]);

    expect($user->can('update', $procedure))->toBeFalse();
});

// delete tests
it('allows admin to delete any procedure', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $procedure = MinorProcedure::factory()->create(['status' => 'in_progress']);

    expect($admin->can('delete', $procedure))->toBeTrue();
});

it('allows admin to delete completed procedure', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $procedure = MinorProcedure::factory()->create(['status' => 'completed']);

    expect($admin->can('delete', $procedure))->toBeTrue();
});

it('denies deleting completed procedures for non-admin', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('minor-procedures.perform');
    $user->departments()->attach($department->id);

    $checkin = PatientCheckin::factory()->create(['department_id' => $department->id]);
    $procedure = MinorProcedure::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'status' => 'completed',
    ]);

    expect($user->can('delete', $procedure))->toBeFalse();
});

it('allows user with perform permission to delete in-progress procedure in their department', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('minor-procedures.perform');
    $user->departments()->attach($department->id);

    $checkin = PatientCheckin::factory()->create(['department_id' => $department->id]);
    $procedure = MinorProcedure::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'status' => 'in_progress',
    ]);

    expect($user->can('delete', $procedure))->toBeTrue();
});

it('denies user to delete procedure in other department', function () {
    $department1 = Department::factory()->create();
    $department2 = Department::factory()->create();

    $user = User::factory()->create();
    $user->givePermissionTo('minor-procedures.perform');
    $user->departments()->attach($department1->id);

    $checkin = PatientCheckin::factory()->create(['department_id' => $department2->id]);
    $procedure = MinorProcedure::factory()->create([
        'patient_checkin_id' => $checkin->id,
        'status' => 'in_progress',
    ]);

    expect($user->can('delete', $procedure))->toBeFalse();
});

// restore and forceDelete tests
it('allows only admin to restore procedures', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $procedure = MinorProcedure::factory()->create();

    expect($admin->can('restore', $procedure))->toBeTrue();
});

it('denies non-admin to restore procedures', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('minor-procedures.perform');

    $procedure = MinorProcedure::factory()->create();

    expect($user->can('restore', $procedure))->toBeFalse();
});

it('allows only admin to force delete procedures', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $procedure = MinorProcedure::factory()->create();

    expect($admin->can('forceDelete', $procedure))->toBeTrue();
});

it('denies non-admin to force delete procedures', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('minor-procedures.perform');

    $procedure = MinorProcedure::factory()->create();

    expect($user->can('forceDelete', $procedure))->toBeFalse();
});
