<?php

use App\Models\Department;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo([
        'departments.view',
        'departments.create',
        'departments.update',
        'departments.delete',
    ]);
});

it('displays departments index page', function () {
    Department::factory()->count(3)->create();

    $response = $this->actingAs($this->admin)->get('/departments');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Departments/Index'));
});

it('displays create department form', function () {
    $response = $this->actingAs($this->admin)->get('/departments/create');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Departments/Create'));
});

it('creates a new department', function () {
    $response = $this->actingAs($this->admin)->post('/departments', [
        'name' => 'Test Department',
        'code' => 'TEST',
        'description' => 'Test description',
        'type' => 'opd',
        'is_active' => true,
    ]);

    $response->assertRedirect('/departments');
    expect(Department::where('code', 'TEST')->exists())->toBeTrue();
});

it('validates required fields on create', function () {
    $response = $this->actingAs($this->admin)->post('/departments', []);

    $response->assertSessionHasErrors(['name', 'code', 'type']);
});

it('validates unique code', function () {
    Department::factory()->create(['code' => 'EXIST']);

    $response = $this->actingAs($this->admin)->post('/departments', [
        'name' => 'New Department',
        'code' => 'EXIST',
        'type' => 'opd',
    ]);

    $response->assertSessionHasErrors(['code']);
});

it('displays edit department form', function () {
    $department = Department::factory()->create();

    $response = $this->actingAs($this->admin)->get("/departments/{$department->id}/edit");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Departments/Edit')
        ->has('department')
    );
});

it('updates a department', function () {
    $department = Department::factory()->create(['name' => 'Old Name']);

    $response = $this->actingAs($this->admin)->put("/departments/{$department->id}", [
        'name' => 'New Name',
        'code' => $department->code,
        'type' => 'opd',
        'is_active' => true,
    ]);

    $response->assertRedirect('/departments');
    expect($department->fresh()->name)->toBe('New Name');
});

it('deletes a department without associations', function () {
    $department = Department::factory()->create();

    $response = $this->actingAs($this->admin)->delete("/departments/{$department->id}");

    $response->assertRedirect('/departments');
    expect(Department::find($department->id))->toBeNull();
});

it('prevents unauthorized access', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/departments');

    $response->assertForbidden();
});
