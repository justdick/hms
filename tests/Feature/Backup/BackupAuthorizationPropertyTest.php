<?php

/**
 * Property-Based Tests for Backup Authorization
 *
 * **Feature: database-backup, Property 16: Unauthorized Access Denial**
 * **Validates: Requirements 8.1**
 *
 * Property: For any user without the required backup permission, attempting to
 * access a protected backup operation SHALL result in a 403 Forbidden response.
 *
 * **Feature: database-backup, Property 17: Permission Granularity Enforcement**
 * **Validates: Requirements 8.2, 8.3**
 *
 * Property: For any user with only view permission, create/delete/restore
 * operations SHALL be denied while view operations SHALL be allowed.
 */

use App\Models\Backup;
use App\Models\User;
use App\Policies\BackupPolicy;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Create backup permissions
    Permission::firstOrCreate(['name' => 'backups.view']);
    Permission::firstOrCreate(['name' => 'backups.create']);
    Permission::firstOrCreate(['name' => 'backups.delete']);
    Permission::firstOrCreate(['name' => 'backups.restore']);
    Permission::firstOrCreate(['name' => 'backups.manage-settings']);
});

/**
 * Property 16: Unauthorized Access Denial
 *
 * For any user without backup permissions, all backup operations should be denied.
 */
describe('Property 16: Unauthorized Access Denial', function () {
    dataset('backup_operations', [
        'view list' => ['get', '/admin/backups', null],
        'create backup' => ['post', '/admin/backups', null],
        'view settings' => ['get', '/admin/backups/settings', null],
        'update settings' => ['put', '/admin/backups/settings', null],
    ]);

    it('denies access to users without any backup permissions', function (string $method, string $url, ?int $backupId) {
        // Arrange - User with no backup permissions
        $user = User::factory()->create();

        // Create a backup for operations that need one
        if ($backupId === null && str_contains($url, '{backup}')) {
            $backup = Backup::factory()->create();
            $url = str_replace('{backup}', $backup->id, $url);
        }

        // Act
        $response = $this->actingAs($user)->$method($url);

        // Assert - Should be forbidden (403)
        $response->assertForbidden();
    })->with('backup_operations');

    it('denies delete operation to users without delete permission', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('backups.view'); // Has view but not delete

        $backup = Backup::factory()->create();

        // Act
        $response = $this->actingAs($user)
            ->delete("/admin/backups/{$backup->id}");

        // Assert
        $response->assertForbidden();
        expect(Backup::find($backup->id))->not->toBeNull();
    });

    it('denies restore operation to users without restore permission', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('backups.view'); // Has view but not restore

        $backup = Backup::factory()->completed()->create();

        // Act
        $response = $this->actingAs($user)
            ->post("/admin/backups/{$backup->id}/restore");

        // Assert
        $response->assertForbidden();
    });

    it('denies settings management to users without manage-settings permission', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('backups.view'); // Has view but not manage-settings

        // Act
        $response = $this->actingAs($user)
            ->put('/admin/backups/settings', [
                'schedule_enabled' => true,
                'schedule_frequency' => 'daily',
                'schedule_time' => '02:00',
                'retention_daily' => 7,
                'retention_weekly' => 4,
                'retention_monthly' => 3,
            ]);

        // Assert
        $response->assertForbidden();
    });
});

/**
 * Property 17: Permission Granularity Enforcement
 *
 * Users with only view permission can view backups but cannot create, delete, or restore.
 */
describe('Property 17: Permission Granularity Enforcement', function () {
    it('allows view-only users to view backup list', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('backups.view');

        Backup::factory()->count(3)->create();

        // Act - Test policy directly since frontend page doesn't exist yet
        $policy = new BackupPolicy;
        $canView = $policy->viewAny($user);

        // Assert - Should be allowed
        expect($canView)->toBeTrue();
    });

    it('denies view-only users from creating backups', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('backups.view');

        // Act
        $response = $this->actingAs($user)
            ->post('/admin/backups');

        // Assert
        $response->assertForbidden();
        expect(Backup::count())->toBe(0);
    });

    it('denies view-only users from deleting backups', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('backups.view');

        $backup = Backup::factory()->create();

        // Act
        $response = $this->actingAs($user)
            ->delete("/admin/backups/{$backup->id}");

        // Assert
        $response->assertForbidden();
        expect(Backup::find($backup->id))->not->toBeNull();
    });

    it('denies view-only users from restoring backups', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('backups.view');

        $backup = Backup::factory()->completed()->create();

        // Act
        $response = $this->actingAs($user)
            ->post("/admin/backups/{$backup->id}/restore");

        // Assert
        $response->assertForbidden();
    });

    it('allows users with create permission to create backups', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('backups.view');
        $user->givePermissionTo('backups.create');

        // Act
        $response = $this->actingAs($user)
            ->post('/admin/backups');

        // Assert - Should not be forbidden (may redirect or succeed)
        expect($response->status())->not->toBe(403);
    });

    it('allows users with delete permission to delete backups', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('backups.view');
        $user->givePermissionTo('backups.delete');

        $backup = Backup::factory()->create();

        // Act
        $response = $this->actingAs($user)
            ->delete("/admin/backups/{$backup->id}");

        // Assert - Should not be forbidden (may redirect or succeed)
        expect($response->status())->not->toBe(403);
    });

    it('allows users with manage-settings permission to update settings', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('backups.view');
        $user->givePermissionTo('backups.manage-settings');

        // Act
        $response = $this->actingAs($user)
            ->put('/admin/backups/settings', [
                'schedule_enabled' => true,
                'schedule_frequency' => 'daily',
                'schedule_time' => '02:00',
                'retention_daily' => 7,
                'retention_weekly' => 4,
                'retention_monthly' => 3,
            ]);

        // Assert - Should not be forbidden
        expect($response->status())->not->toBe(403);
    });

    dataset('permission_combinations', [
        'view only' => [['backups.view'], ['view' => true, 'create' => false, 'delete' => false, 'restore' => false]],
        'view and create' => [['backups.view', 'backups.create'], ['view' => true, 'create' => true, 'delete' => false, 'restore' => false]],
        'view and delete' => [['backups.view', 'backups.delete'], ['view' => true, 'create' => false, 'delete' => true, 'restore' => false]],
        'view and restore' => [['backups.view', 'backups.restore'], ['view' => true, 'create' => false, 'delete' => false, 'restore' => true]],
        'all permissions' => [['backups.view', 'backups.create', 'backups.delete', 'backups.restore'], ['view' => true, 'create' => true, 'delete' => true, 'restore' => true]],
    ]);

    it('enforces correct permission combinations', function (array $permissions, array $expectedAccess) {
        // Arrange
        $user = User::factory()->create();
        foreach ($permissions as $permission) {
            $user->givePermissionTo($permission);
        }

        $backup = Backup::factory()->completed()->create();
        $policy = new BackupPolicy;

        // Test view - use policy directly since frontend page doesn't exist yet
        $canView = $policy->viewAny($user);
        if ($expectedAccess['view']) {
            expect($canView)->toBeTrue();
        } else {
            expect($canView)->toBeFalse();
        }

        // Test create
        $createResponse = $this->actingAs($user)->post('/admin/backups');
        if ($expectedAccess['create']) {
            expect($createResponse->status())->not->toBe(403);
        } else {
            $createResponse->assertForbidden();
        }

        // Test delete
        $deleteResponse = $this->actingAs($user)->delete("/admin/backups/{$backup->id}");
        if ($expectedAccess['delete']) {
            expect($deleteResponse->status())->not->toBe(403);
        } else {
            $deleteResponse->assertForbidden();
        }

        // Test restore (need a fresh backup since delete might have removed it)
        $restoreBackup = Backup::factory()->completed()->create();
        $restoreResponse = $this->actingAs($user)->post("/admin/backups/{$restoreBackup->id}/restore");
        if ($expectedAccess['restore']) {
            expect($restoreResponse->status())->not->toBe(403);
        } else {
            $restoreResponse->assertForbidden();
        }
    })->with('permission_combinations');
});
