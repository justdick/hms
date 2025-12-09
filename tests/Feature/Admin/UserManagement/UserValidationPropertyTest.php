<?php

/**
 * Property-Based Tests for User Validation
 *
 * These tests verify the correctness properties of user validation
 * as defined in the design document.
 */

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Create the Admin role for authorization
    Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
});

/**
 * Property 2: Username uniqueness constraint
 *
 * **Feature: user-management, Property 2: Username uniqueness constraint**
 * **Validates: Requirements 2.3, 3.4**
 *
 * For any user creation or update request, if the username already exists
 * for a different user, the system SHALL reject the request with a validation error.
 */
describe('Property 2: Username uniqueness constraint', function () {
    it('rejects user creation with duplicate username', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $existingUser = User::factory()->create([
            'username' => 'existinguser',
        ]);

        $doctorRole = Role::firstOrCreate(['name' => 'Doctor', 'guard_name' => 'web']);

        // Act - Try to create user with same username
        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New User',
            'username' => 'existinguser', // Duplicate username
            'roles' => ['Doctor'],
        ]);

        // Assert - Should fail validation
        $response->assertSessionHasErrors('username');
    });

    it('rejects user update with duplicate username', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $existingUser = User::factory()->create([
            'username' => 'existinguser',
        ]);

        $userToUpdate = User::factory()->create([
            'username' => 'originaluser',
        ]);

        $doctorRole = Role::firstOrCreate(['name' => 'Doctor', 'guard_name' => 'web']);
        $userToUpdate->assignRole('Doctor');

        // Act - Try to update user with existing username
        $response = $this->actingAs($admin)->put("/admin/users/{$userToUpdate->id}", [
            'name' => 'Updated Name',
            'username' => 'existinguser', // Duplicate username
            'roles' => ['Doctor'],
        ]);

        // Assert - Should fail validation
        $response->assertSessionHasErrors('username');
    });

    it('allows user update with same username (own username)', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $userToUpdate = User::factory()->create([
            'username' => 'myusername',
        ]);

        $doctorRole = Role::firstOrCreate(['name' => 'Doctor', 'guard_name' => 'web']);
        $userToUpdate->assignRole('Doctor');

        // Act - Update user keeping same username
        $response = $this->actingAs($admin)->put("/admin/users/{$userToUpdate->id}", [
            'name' => 'Updated Name',
            'username' => 'myusername', // Same username
            'roles' => ['Doctor'],
        ]);

        // Assert - Should succeed (no username error)
        $response->assertSessionDoesntHaveErrors('username');
    });

    it('rejects duplicate username for any randomly generated username', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        // Generate random username (alphanumeric, min 4 chars)
        $randomUsername = 'user'.fake()->unique()->randomNumber(6);

        $existingUser = User::factory()->create([
            'username' => $randomUsername,
        ]);

        $doctorRole = Role::firstOrCreate(['name' => 'Doctor', 'guard_name' => 'web']);

        // Act - Try to create user with same random username
        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => fake()->name(),
            'username' => $randomUsername, // Duplicate
            'roles' => ['Doctor'],
        ]);

        // Assert
        $response->assertSessionHasErrors('username');
    })->repeat(10);

    it('accepts unique username for any randomly generated username', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $doctorRole = Role::firstOrCreate(['name' => 'Doctor', 'guard_name' => 'web']);

        // Generate unique random username (alphanumeric, min 4 chars)
        $uniqueUsername = 'user'.fake()->unique()->randomNumber(6);

        // Act - Create user with unique username (don't follow redirects)
        $response = $this->actingAs($admin)
            ->withoutMiddleware(\Illuminate\Routing\Middleware\SubstituteBindings::class)
            ->from('/admin/users/create')
            ->post('/admin/users', [
                'name' => fake()->name(),
                'username' => $uniqueUsername,
                'roles' => ['Doctor'],
            ]);

        // Assert - Should redirect (success) and not have username error
        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors('username');
    })->repeat(10);
});

/**
 * Property 1: User creation assigns at least one role
 *
 * **Feature: user-management, Property 1: User creation assigns at least one role**
 * **Validates: Requirements 2.4**
 *
 * For any user creation request with valid data, the created user
 * SHALL have at least one role assigned in the database.
 */
describe('Property 1: User creation assigns at least one role', function () {
    it('rejects user creation without roles', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        // Act - Try to create user without roles
        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New User',
            'username' => 'newuser1',
            'roles' => [], // Empty roles
        ]);

        // Assert - Should fail validation
        $response->assertSessionHasErrors('roles');
    });

    it('rejects user creation with null roles', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        // Act - Try to create user without roles field
        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New User',
            'username' => 'newuser2',
            // roles field missing
        ]);

        // Assert - Should fail validation
        $response->assertSessionHasErrors('roles');
    });

    it('creates user with at least one role assigned', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $doctorRole = Role::firstOrCreate(['name' => 'Doctor', 'guard_name' => 'web']);

        // Act - Create user with one role
        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New Doctor',
            'username' => 'newdoctor',
            'roles' => ['Doctor'],
        ]);

        // Assert - User should have the role
        $newUser = User::where('username', 'newdoctor')->first();
        expect($newUser)->not->toBeNull();
        expect($newUser->roles->count())->toBeGreaterThanOrEqual(1);
        expect($newUser->hasRole('Doctor'))->toBeTrue();
    });

    it('creates user with multiple roles assigned', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        Role::firstOrCreate(['name' => 'Doctor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Nurse', 'guard_name' => 'web']);

        // Act - Create user with multiple roles
        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Multi-Role User',
            'username' => 'multirole',
            'roles' => ['Doctor', 'Nurse'],
        ]);

        // Assert - User should have all roles
        $newUser = User::where('username', 'multirole')->first();
        expect($newUser)->not->toBeNull();
        expect($newUser->roles->count())->toBe(2);
        expect($newUser->hasRole('Doctor'))->toBeTrue();
        expect($newUser->hasRole('Nurse'))->toBeTrue();
    });

    it('rejects user update that removes all roles', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $doctorRole = Role::firstOrCreate(['name' => 'Doctor', 'guard_name' => 'web']);

        $userToUpdate = User::factory()->create();
        $userToUpdate->assignRole('Doctor');

        // Act - Try to update user removing all roles
        $response = $this->actingAs($admin)->put("/admin/users/{$userToUpdate->id}", [
            'name' => 'Updated Name',
            'username' => $userToUpdate->username,
            'roles' => [], // Empty roles
        ]);

        // Assert - Should fail validation
        $response->assertSessionHasErrors('roles');
    });

    it('ensures created user always has at least one role for any valid role', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        // Create a random role
        $roleName = 'TestRole_'.uniqid();
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

        $username = 'user'.fake()->unique()->randomNumber(6);

        // Act - Create user with the role
        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => fake()->name(),
            'username' => $username,
            'roles' => [$roleName],
        ]);

        // Assert - User should have at least one role
        $newUser = User::where('username', $username)->first();
        expect($newUser)->not->toBeNull();
        expect($newUser->roles->count())->toBeGreaterThanOrEqual(1);
    })->repeat(10);
});

/**
 * Property 3: Deactivated users cannot authenticate
 *
 * **Feature: user-management, Property 3: Deactivated users cannot authenticate**
 * **Validates: Requirements 4.1, 4.4**
 *
 * For any user with `is_active = false`, authentication attempts
 * SHALL fail regardless of correct credentials.
 */
describe('Property 3: Deactivated users cannot authenticate', function () {
    it('rejects login for deactivated user with correct credentials', function () {
        // Arrange
        $password = 'SecurePassword123!';
        $user = User::factory()->create([
            'username' => 'deactivated',
            'password' => bcrypt($password),
            'is_active' => false,
        ]);

        // Act - Try to login with correct credentials
        $response = $this->post('/login', [
            'username' => 'deactivated',
            'password' => $password,
        ]);

        // Assert - Should fail with appropriate message
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    });

    it('allows login for active user with correct credentials', function () {
        // Arrange
        $password = 'SecurePassword123!';
        $user = User::factory()->create([
            'username' => 'activeuser',
            'password' => bcrypt($password),
            'is_active' => true,
        ]);

        // Act - Login with correct credentials
        $response = $this->post('/login', [
            'username' => 'activeuser',
            'password' => $password,
        ]);

        // Assert - Should succeed
        $response->assertSessionDoesntHaveErrors('username');
        $this->assertAuthenticated();
    });

    it('rejects login for any deactivated user regardless of credentials', function () {
        // Arrange
        $password = fake()->password(12);
        $username = 'user'.fake()->unique()->randomNumber(6);

        $user = User::factory()->create([
            'username' => $username,
            'password' => bcrypt($password),
            'is_active' => false,
        ]);

        // Act - Try to login
        $response = $this->post('/login', [
            'username' => $username,
            'password' => $password,
        ]);

        // Assert - Should fail
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    })->repeat(10);

    it('displays appropriate error message for deactivated account', function () {
        // Arrange
        $password = 'SecurePassword123!';
        $user = User::factory()->create([
            'username' => 'deactivatedmsg',
            'password' => bcrypt($password),
            'is_active' => false,
        ]);

        // Act
        $response = $this->post('/login', [
            'username' => 'deactivatedmsg',
            'password' => $password,
        ]);

        // Assert - Should have error message about deactivation
        $response->assertSessionHasErrors('username');
        $errors = session('errors')->get('username');
        expect($errors[0])->toContain('deactivated');
    });

    it('prevents authentication even after reactivation and deactivation cycle', function () {
        // Arrange
        $password = 'SecurePassword123!';
        $user = User::factory()->create([
            'username' => 'cycleuser',
            'password' => bcrypt($password),
            'is_active' => true,
        ]);

        // First, verify user can login when active
        $response = $this->post('/login', [
            'username' => 'cycleuser',
            'password' => $password,
        ]);
        $this->assertAuthenticated();

        // Logout
        $this->post('/logout');

        // Deactivate user
        $user->update(['is_active' => false]);

        // Act - Try to login again
        $response = $this->post('/login', [
            'username' => 'cycleuser',
            'password' => $password,
        ]);

        // Assert - Should fail
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    });
});
