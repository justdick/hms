<?php

/**
 * Property-Based Tests for RolePolicy
 *
 * These tests verify the correctness properties of the role policy
 * as defined in the design document.
 */

use App\Models\User;
use App\Policies\RolePolicy;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Create necessary permissions for role management
    Permission::firstOrCreate(['name' => 'roles.view-all', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'roles.create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'roles.update', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'roles.delete', 'guard_name' => 'web']);

    // Create Admin role if it doesn't exist
    Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
});

/**
 * Property 7: Role deletion blocked when users assigned
 *
 * **Feature: user-management, Property 7: Role deletion blocked when users assigned**
 * **Validates: Requirements 6.4**
 *
 * For any role deletion request, if the role has one or more users assigned,
 * the system SHALL reject the deletion.
 */
describe('Property 7: Role deletion blocked when users assigned', function () {
    it('prevents deletion of role with assigned users', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->givePermissionTo('roles.delete');

        $roleToDelete = Role::create(['name' => 'TestRole', 'guard_name' => 'web']);
        $userWithRole = User::factory()->create();
        $userWithRole->assignRole($roleToDelete);

        $policy = new RolePolicy;

        // Act & Assert - Cannot delete role with users assigned
        expect($policy->delete($admin, $roleToDelete))->toBeFalse();
    });

    it('allows deletion of role without assigned users', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->givePermissionTo('roles.delete');

        $roleToDelete = Role::create(['name' => 'EmptyRole', 'guard_name' => 'web']);

        $policy = new RolePolicy;

        // Act & Assert - Can delete role without users
        expect($policy->delete($admin, $roleToDelete))->toBeTrue();
    });

    it('prevents deletion when multiple users are assigned to role', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->givePermissionTo('roles.delete');

        $roleToDelete = Role::create(['name' => 'PopularRole', 'guard_name' => 'web']);

        // Assign multiple users to the role
        $users = User::factory()->count(3)->create();
        foreach ($users as $user) {
            $user->assignRole($roleToDelete);
        }

        $policy = new RolePolicy;

        // Act & Assert - Cannot delete role with multiple users
        expect($policy->delete($admin, $roleToDelete))->toBeFalse();
    });

    it('allows deletion after all users are removed from role', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->givePermissionTo('roles.delete');

        $roleToDelete = Role::create(['name' => 'TransientRole', 'guard_name' => 'web']);
        $userWithRole = User::factory()->create();
        $userWithRole->assignRole($roleToDelete);

        $policy = new RolePolicy;

        // Initially cannot delete
        expect($policy->delete($admin, $roleToDelete))->toBeFalse();

        // Remove user from role
        $userWithRole->removeRole($roleToDelete);

        // Now can delete
        expect($policy->delete($admin, $roleToDelete))->toBeTrue();
    });

    it('denies deletion for user without permission even if role has no users', function () {
        // Arrange
        $userWithoutPermission = User::factory()->create();
        $roleToDelete = Role::create(['name' => 'UnprotectedRole', 'guard_name' => 'web']);

        $policy = new RolePolicy;

        // Act & Assert - Cannot delete without permission
        expect($policy->delete($userWithoutPermission, $roleToDelete))->toBeFalse();
    });

    it('allows Admin role to delete roles without users', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $roleToDelete = Role::create(['name' => 'AdminDeletableRole', 'guard_name' => 'web']);

        $policy = new RolePolicy;

        // Act & Assert - Admin can delete role without users
        expect($policy->delete($admin, $roleToDelete))->toBeTrue();
    });

    it('prevents Admin from deleting role with users', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $roleToDelete = Role::create(['name' => 'AdminBlockedRole', 'guard_name' => 'web']);
        $userWithRole = User::factory()->create();
        $userWithRole->assignRole($roleToDelete);

        $policy = new RolePolicy;

        // Act & Assert - Even Admin cannot delete role with users
        expect($policy->delete($admin, $roleToDelete))->toBeFalse();
    });
});

/**
 * Additional authorization tests for RolePolicy
 */
describe('RolePolicy authorization', function () {
    it('allows viewAny for users with roles.view-all permission', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('roles.view-all');
        $policy = new RolePolicy;

        // Act & Assert
        expect($policy->viewAny($user))->toBeTrue();
    });

    it('allows viewAny for Admin role', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $policy = new RolePolicy;

        // Act & Assert
        expect($policy->viewAny($admin))->toBeTrue();
    });

    it('denies viewAny for users without permission', function () {
        // Arrange
        $user = User::factory()->create();
        $policy = new RolePolicy;

        // Act & Assert
        expect($policy->viewAny($user))->toBeFalse();
    });

    it('allows create for users with roles.create permission', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('roles.create');
        $policy = new RolePolicy;

        // Act & Assert
        expect($policy->create($user))->toBeTrue();
    });

    it('denies create for users without permission', function () {
        // Arrange
        $user = User::factory()->create();
        $policy = new RolePolicy;

        // Act & Assert
        expect($policy->create($user))->toBeFalse();
    });

    it('allows update for users with roles.update permission', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('roles.update');
        $role = Role::create(['name' => 'UpdatableRole', 'guard_name' => 'web']);
        $policy = new RolePolicy;

        // Act & Assert
        expect($policy->update($user, $role))->toBeTrue();
    });

    it('denies update for users without permission', function () {
        // Arrange
        $user = User::factory()->create();
        $role = Role::create(['name' => 'ProtectedRole', 'guard_name' => 'web']);
        $policy = new RolePolicy;

        // Act & Assert
        expect($policy->update($user, $role))->toBeFalse();
    });

    it('allows view for users with roles.view-all permission', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('roles.view-all');
        $role = Role::create(['name' => 'ViewableRole', 'guard_name' => 'web']);
        $policy = new RolePolicy;

        // Act & Assert
        expect($policy->view($user, $role))->toBeTrue();
    });
});
