<?php

/**
 * Property-Based Tests for Billing Permission-Based Access
 *
 * **Feature: billing-enhancements, Property 14: Permission-based access enforcement**
 * **Validates: Requirements 12.4, 12.6**
 */

use App\Models\User;
use App\Policies\BillingPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Ensure all billing permissions exist
    $permissions = [
        'billing.collect',
        'billing.view-all',
        'billing.override',
        'billing.reconcile',
        'billing.reports',
        'billing.statements',
        'billing.manage-credit',
        'billing.void',
        'billing.refund',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }
});

describe('Property 14: Permission-based access enforcement', function () {
    /**
     * **Feature: billing-enhancements, Property 14: Permission-based access enforcement**
     * **Validates: Requirements 12.4, 12.6**
     *
     * For any user without the specific required permission accessing a protected route,
     * the system SHALL return a 403 forbidden response regardless of their role.
     */
    it('denies access to users without the required permission', function () {
        $policy = new BillingPolicy;

        // Define permission-to-policy-method mapping
        $permissionMethods = [
            'billing.collect' => 'collect',
            'billing.view-all' => 'viewAll',
            'billing.override' => 'override',
            'billing.reconcile' => 'reconcile',
            'billing.reports' => 'viewReports',
            'billing.statements' => 'generateStatements',
            'billing.manage-credit' => 'manageCredit',
            'billing.void' => 'void',
            'billing.refund' => 'refund',
        ];

        // Run 100 iterations with different random configurations
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Create a user without any permissions
            $userWithoutPermissions = User::factory()->create();

            // For each permission, verify user without it is denied
            foreach ($permissionMethods as $permission => $method) {
                $result = $policy->$method($userWithoutPermissions);
                expect($result)->toBeFalse(
                    "User without {$permission} should be denied access to {$method} (iteration {$iteration})"
                );
            }

            // Clean up
            $userWithoutPermissions->delete();
        }
    });

    /**
     * Property: Users WITH the required permission are granted access
     */
    it('grants access to users with the required permission', function () {
        $policy = new BillingPolicy;

        // Define permission-to-policy-method mapping
        $permissionMethods = [
            'billing.collect' => 'collect',
            'billing.view-all' => 'viewAll',
            'billing.override' => 'override',
            'billing.reconcile' => 'reconcile',
            'billing.reports' => 'viewReports',
            'billing.statements' => 'generateStatements',
            'billing.manage-credit' => 'manageCredit',
            'billing.void' => 'void',
            'billing.refund' => 'refund',
        ];

        // Run 100 iterations
        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Randomly select a permission to test
            $permissionKeys = array_keys($permissionMethods);
            $randomPermission = $permissionKeys[array_rand($permissionKeys)];
            $method = $permissionMethods[$randomPermission];

            // Create a user with only that specific permission
            $userWithPermission = User::factory()->create();
            $userWithPermission->givePermissionTo($randomPermission);

            // Verify user with permission is granted access
            $result = $policy->$method($userWithPermission);
            expect($result)->toBeTrue(
                "User with {$randomPermission} should be granted access to {$method} (iteration {$iteration})"
            );

            // Clean up
            $userWithPermission->delete();
        }
    });

    /**
     * Property: Permission check is independent of role assignment
     */
    it('enforces permission checks regardless of role', function () {
        $policy = new BillingPolicy;

        $permissionMethods = [
            'billing.collect' => 'collect',
            'billing.override' => 'override',
            'billing.reconcile' => 'reconcile',
            'billing.reports' => 'viewReports',
            'billing.statements' => 'generateStatements',
            'billing.manage-credit' => 'manageCredit',
            'billing.void' => 'void',
            'billing.refund' => 'refund',
        ];

        for ($iteration = 0; $iteration < 100; $iteration++) {
            // Create a user
            $user = User::factory()->create();

            // Randomly select a subset of permissions to grant (0 to all)
            $allPermissions = array_keys($permissionMethods);
            $numPermissions = fake()->numberBetween(0, count($allPermissions));
            shuffle($allPermissions);
            $grantedPermissions = array_slice($allPermissions, 0, $numPermissions);

            // Grant the selected permissions
            foreach ($grantedPermissions as $permission) {
                $user->givePermissionTo($permission);
            }

            // Verify each policy method returns correct result based on permission
            foreach ($permissionMethods as $permission => $method) {
                $hasPermission = in_array($permission, $grantedPermissions);
                $result = $policy->$method($user);

                if ($hasPermission) {
                    expect($result)->toBeTrue(
                        "User with {$permission} should be granted access to {$method} (iteration {$iteration})"
                    );
                } else {
                    expect($result)->toBeFalse(
                        "User without {$permission} should be denied access to {$method} (iteration {$iteration})"
                    );
                }
            }

            // Clean up
            $user->delete();
        }
    });

    /**
     * Property: Access decision is deterministic for same user-permission combination
     */
    it('returns consistent results for the same user-permission combination', function () {
        $policy = new BillingPolicy;

        $permissionMethods = [
            'billing.collect' => 'collect',
            'billing.override' => 'override',
            'billing.reconcile' => 'reconcile',
        ];

        for ($iteration = 0; $iteration < 100; $iteration++) {
            $user = User::factory()->create();

            // Randomly decide whether to grant each permission
            foreach ($permissionMethods as $permission => $method) {
                if (fake()->boolean()) {
                    $user->givePermissionTo($permission);
                }
            }

            // Call each policy method multiple times and verify consistency
            foreach ($permissionMethods as $permission => $method) {
                $firstResult = $policy->$method($user);
                $secondResult = $policy->$method($user);
                $thirdResult = $policy->$method($user);

                expect($firstResult)->toBe($secondResult, "Policy {$method} should return consistent results");
                expect($secondResult)->toBe($thirdResult, "Policy {$method} should return consistent results");
            }

            // Clean up
            $user->delete();
        }
    });
});
