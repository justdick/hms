<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

it('creates minor procedures permissions', function () {
    $permissions = Permission::whereIn('name', [
        'minor-procedures.view-dept',
        'minor-procedures.perform',
        'minor-procedures.view-all',
    ])->get();

    expect($permissions)->toHaveCount(3);
    expect($permissions->pluck('name')->toArray())->toContain(
        'minor-procedures.view-dept',
        'minor-procedures.perform',
        'minor-procedures.view-all'
    );
});

it('assigns minor procedures permissions to nurse role', function () {
    $nurse = Role::where('name', 'Nurse')->first();

    expect($nurse->hasPermissionTo('minor-procedures.view-dept'))->toBeTrue();
    expect($nurse->hasPermissionTo('minor-procedures.perform'))->toBeTrue();
});

it('assigns view-all permission to admin role', function () {
    $admin = Role::where('name', 'Admin')->first();

    expect($admin->hasPermissionTo('minor-procedures.view-all'))->toBeTrue();
});

it('nurse user can perform minor procedures', function () {
    $nurse = User::factory()->create();
    $nurse->assignRole('Nurse');

    expect($nurse->can('minor-procedures.view-dept'))->toBeTrue();
    expect($nurse->can('minor-procedures.perform'))->toBeTrue();
});

it('admin user has all minor procedures permissions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    expect($admin->can('minor-procedures.view-dept'))->toBeTrue();
    expect($admin->can('minor-procedures.perform'))->toBeTrue();
    expect($admin->can('minor-procedures.view-all'))->toBeTrue();
});

it('receptionist does not have minor procedures permissions', function () {
    $receptionist = User::factory()->create();
    $receptionist->assignRole('Receptionist');

    expect($receptionist->can('minor-procedures.view-dept'))->toBeFalse();
    expect($receptionist->can('minor-procedures.perform'))->toBeFalse();
});
