<?php

/**
 * Property-Based Tests for UserPolicy
 *
 * These tests verify the correctness properties of the user policy
 * as defined in the design document.
 */

use App\Models\User;
use App\Policies\UserPolicy;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Create necessary permissions
    Permission::firstOrCreate(['name' => 'users.view-all', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'users.create', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'users.update', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'users.reset-password', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'users.delete', 'guard_name' => 'web']);

    // Create Admin role if it doesn't exist
    Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
});

/**
 * Property 4: Self-deactivation prevention
 *
 * **Feature: user-management, Property 4: Self-deactivation prevention**
 * **Validates: Requirements 4.3**
 *
 * For any deactivation request where the target user is the authenticated user,
 * the system SHALL reject the request.
 */
describe('Property 4: Self-deactivation prevention', function () {
    it('prevents user from deactivating themselves regardless of permissions', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('users.update');
        $policy = new UserPolicy;

        // Act & Assert - User cannot toggle their own active status
        expect($policy->toggleActive($user, $user))->toBeFalse();
    });

    it('prevents admin from deactivating themselves', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $policy = new UserPolicy;

        // Act & Assert - Admin cannot toggle their own active status
        expect($policy->toggleActive($admin, $admin))->toBeFalse();
    });

    it('allows user with permission to deactivate other users', function () {
        // Arrange
        $actor = User::factory()->create();
        $actor->givePermissionTo('users.update');
        $targetUser = User::factory()->create();
        $policy = new UserPolicy;

        // Act & Assert - User can toggle another user's active status
        expect($policy->toggleActive($actor, $targetUser))->toBeTrue();
    });

    it('allows admin to deactivate other users', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $targetUser = User::factory()->create();
        $policy = new UserPolicy;

        // Act & Assert - Admin can toggle another user's active status
        expect($policy->toggleActive($admin, $targetUser))->toBeTrue();
    });

    it('prevents self-deactivation for any user regardless of role', function (string $roleName) {
        // Arrange
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $role->givePermissionTo('users.update');

        $user = User::factory()->create();
        $user->assignRole($roleName);
        $policy = new UserPolicy;

        // Act & Assert - User cannot toggle their own active status
        expect($policy->toggleActive($user, $user))->toBeFalse();
    })->with(['Admin', 'Manager', 'Supervisor']);

    it('denies toggle active for user without permission on other users', function () {
        // Arrange
        $actor = User::factory()->create(); // No permissions
        $targetUser = User::factory()->create();
        $policy = new UserPolicy;

        // Act & Assert - User without permission cannot toggle another user's status
        expect($policy->toggleActive($actor, $targetUser))->toBeFalse();
    });

    it('self-deactivation prevention applies regardless of target user active status', function (bool $isActive) {
        // Arrange
        $user = User::factory()->create(['is_active' => $isActive]);
        $user->givePermissionTo('users.update');
        $policy = new UserPolicy;

        // Act & Assert - User cannot toggle their own status regardless of current state
        expect($policy->toggleActive($user, $user))->toBeFalse();
    })->with([true, false]);

    it('prevents self-deletion as well', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('users.delete');
        $policy = new UserPolicy;

        // Act & Assert - User cannot delete themselves
        expect($policy->delete($user, $user))->toBeFalse();
    });

    it('allows deletion of other users with permission', function () {
        // Arrange
        $actor = User::factory()->create();
        $actor->givePermissionTo('users.delete');
        $targetUser = User::factory()->create();
        $policy = new UserPolicy;

        // Act & Assert - User can delete another user
        expect($policy->delete($actor, $targetUser))->toBeTrue();
    });
});

/**
 * Additional authorization tests for UserPolicy
 */
describe('UserPolicy authorization', function () {
    it('allows viewAny for users with users.view-all permission', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('users.view-all');
        $policy = new UserPolicy;

        // Act & Assert
        expect($policy->viewAny($user))->toBeTrue();
    });

    it('allows viewAny for Admin role', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $policy = new UserPolicy;

        // Act & Assert
        expect($policy->viewAny($admin))->toBeTrue();
    });

    it('denies viewAny for users without permission', function () {
        // Arrange
        $user = User::factory()->create();
        $policy = new UserPolicy;

        // Act & Assert
        expect($policy->viewAny($user))->toBeFalse();
    });

    it('allows create for users with users.create permission', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('users.create');
        $policy = new UserPolicy;

        // Act & Assert
        expect($policy->create($user))->toBeTrue();
    });

    it('allows update for users with users.update permission', function () {
        // Arrange
        $actor = User::factory()->create();
        $actor->givePermissionTo('users.update');
        $targetUser = User::factory()->create();
        $policy = new UserPolicy;

        // Act & Assert
        expect($policy->update($actor, $targetUser))->toBeTrue();
    });

    it('allows resetPassword for users with users.reset-password permission', function () {
        // Arrange
        $actor = User::factory()->create();
        $actor->givePermissionTo('users.reset-password');
        $targetUser = User::factory()->create();
        $policy = new UserPolicy;

        // Act & Assert
        expect($policy->resetPassword($actor, $targetUser))->toBeTrue();
    });

    it('denies resetPassword for users without permission', function () {
        // Arrange
        $actor = User::factory()->create();
        $targetUser = User::factory()->create();
        $policy = new UserPolicy;

        // Act & Assert
        expect($policy->resetPassword($actor, $targetUser))->toBeFalse();
    });
});
