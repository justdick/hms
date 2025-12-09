<?php

/**
 * Property-Based Tests for BackupController
 *
 * These tests verify the correctness properties of the backup controller
 * as defined in the design document.
 */

use App\Models\Backup;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    // Use fake storage for tests
    Storage::fake('local');

    // Create the backups directory
    Storage::disk('local')->makeDirectory('backups');

    // Create backup permissions
    Permission::firstOrCreate(['name' => 'backups.view']);
    Permission::firstOrCreate(['name' => 'backups.create']);
    Permission::firstOrCreate(['name' => 'backups.delete']);
    Permission::firstOrCreate(['name' => 'backups.restore']);
    Permission::firstOrCreate(['name' => 'backups.manage-settings']);

    // Disable Vite for testing (avoids manifest issues)
    $this->withoutVite();
});

/**
 * Property 6: Backup List Data Completeness
 *
 * **Feature: database-backup, Property 6: Backup List Data Completeness**
 * **Validates: Requirements 3.1**
 *
 * For any set of backups in the system, the backup list response SHALL include
 * timestamp, file_size, storage location indicators, and status for each backup.
 */
describe('Property 6: Backup List Data Completeness', function () {
    it('returns complete data for all backups in the list', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('backups.view');

        // Create backups with various states
        $backups = [
            Backup::factory()->completed()->create(),
            Backup::factory()->pending()->create(),
            Backup::factory()->failed()->create(),
            Backup::factory()->onGoogleDrive()->create(),
        ];

        // Act
        $response = $this->actingAs($user)->get('/admin/backups');

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Backup/Index')
            ->has('backups.data', count($backups))
            ->has('backups.data.0', fn (AssertableInertia $backup) => $backup
                ->has('id')
                ->has('filename')
                ->has('file_size')
                ->has('file_path')
                ->has('google_drive_file_id')
                ->has('status')
                ->has('source')
                ->has('is_protected')
                ->has('created_at')
                ->has('completed_at')
                ->etc()
            )
        );
    });

    it('includes all required fields for any backup status', function (string $status) {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('backups.view');

        $backup = Backup::factory()->create(['status' => $status]);

        // Act
        $response = $this->actingAs($user)->get('/admin/backups');

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Backup/Index')
            ->has('backups.data', 1)
            ->where('backups.data.0.status', $status)
            ->has('backups.data.0.created_at')
            ->has('backups.data.0.file_size')
        );
    })->with([
        'completed' => ['completed'],
        'pending' => ['pending'],
        'failed' => ['failed'],
    ]);

    it('includes creator information when available', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('backups.view');

        $creator = User::factory()->create(['name' => 'Test Creator']);
        Backup::factory()->create(['created_by' => $creator->id]);

        // Act
        $response = $this->actingAs($user)->get('/admin/backups');

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Backup/Index')
            ->has('backups.data.0.creator')
            ->where('backups.data.0.creator.id', $creator->id)
            ->where('backups.data.0.creator.name', 'Test Creator')
        );
    });

    it('handles backups without creator gracefully', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('backups.view');

        Backup::factory()->create(['created_by' => null]);

        // Act
        $response = $this->actingAs($user)->get('/admin/backups');

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Backup/Index')
            ->has('backups.data', 1)
            ->where('backups.data.0.creator', null)
        );
    });

    it('returns empty list when no backups exist', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('backups.view');

        // Act
        $response = $this->actingAs($user)->get('/admin/backups');

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Backup/Index')
            ->has('backups.data', 0)
        );
    });

    it('includes pagination metadata', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('backups.view');

        Backup::factory()->count(25)->create();

        // Act
        $response = $this->actingAs($user)->get('/admin/backups');

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Backup/Index')
            ->has('backups.current_page')
            ->has('backups.last_page')
            ->has('backups.per_page')
            ->has('backups.total')
            ->where('backups.total', 25)
        );
    });
});

/**
 * Property 7: Storage Location Accuracy
 *
 * **Feature: database-backup, Property 7: Storage Location Accuracy**
 * **Validates: Requirements 3.2**
 *
 * For any backup, the storage location indicators (isLocal, isOnGoogleDrive)
 * SHALL accurately reflect the actual presence of the backup file in each
 * storage location.
 */
describe('Property 7: Storage Location Accuracy', function () {
    it('accurately indicates local-only storage', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('backups.view');

        $backup = Backup::factory()->create([
            'file_path' => 'backups/test_backup.sql.gz',
            'google_drive_file_id' => null,
            'status' => 'completed',
        ]);

        // Act
        $response = $this->actingAs($user)->get('/admin/backups');

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Backup/Index')
            ->has('backups.data', 1)
            ->where('backups.data.0.file_path', 'backups/test_backup.sql.gz')
            ->where('backups.data.0.google_drive_file_id', null)
        );
    });

    it('accurately indicates Google Drive storage', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('backups.view');

        $backup = Backup::factory()->onGoogleDrive()->create([
            'file_path' => 'backups/test_backup.sql.gz',
        ]);

        // Act
        $response = $this->actingAs($user)->get('/admin/backups');

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Backup/Index')
            ->has('backups.data', 1)
            ->has('backups.data.0.file_path')
            ->has('backups.data.0.google_drive_file_id')
        );

        // Verify the google_drive_file_id is not null
        $pageData = $response->viewData('page')['props']['backups']['data'][0];
        expect($pageData['google_drive_file_id'])->not->toBeNull();
    });

    it('accurately indicates both local and Google Drive storage', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('backups.view');

        $backup = Backup::factory()->create([
            'file_path' => 'backups/test_backup.sql.gz',
            'google_drive_file_id' => 'gdrive-file-123',
            'status' => 'completed',
        ]);

        // Act
        $response = $this->actingAs($user)->get('/admin/backups');

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Backup/Index')
            ->has('backups.data', 1)
            ->where('backups.data.0.file_path', 'backups/test_backup.sql.gz')
            ->where('backups.data.0.google_drive_file_id', 'gdrive-file-123')
        );
    });

    it('accurately indicates no storage for failed backups', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('backups.view');

        // Failed backups may still have a file_path (attempted but failed)
        $backup = Backup::factory()->failed()->create([
            'file_path' => '',
            'google_drive_file_id' => null,
        ]);

        // Act
        $response = $this->actingAs($user)->get('/admin/backups');

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Backup/Index')
            ->has('backups.data', 1)
            ->where('backups.data.0.file_path', '')
            ->where('backups.data.0.google_drive_file_id', null)
            ->where('backups.data.0.status', 'failed')
        );
    });

    it('returns accurate storage indicators for mixed backup states', function () {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('backups.view');

        // Create backups with different storage states
        $localOnly = Backup::factory()->create([
            'file_path' => 'backups/local_only.sql.gz',
            'google_drive_file_id' => null,
            'status' => 'completed',
        ]);

        $googleDriveOnly = Backup::factory()->create([
            'file_path' => '',
            'google_drive_file_id' => 'gdrive-only-123',
            'status' => 'completed',
        ]);

        $both = Backup::factory()->create([
            'file_path' => 'backups/both.sql.gz',
            'google_drive_file_id' => 'gdrive-both-456',
            'status' => 'completed',
        ]);

        // Act
        $response = $this->actingAs($user)->get('/admin/backups');

        // Assert
        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Backup/Index')
            ->has('backups.data', 3)
        );

        // Verify each backup has correct storage indicators
        $pageData = $response->viewData('page')['props']['backups']['data'];

        // Find each backup by ID and verify storage indicators
        $localOnlyData = collect($pageData)->firstWhere('id', $localOnly->id);
        expect($localOnlyData['file_path'])->not->toBeNull();
        expect($localOnlyData['google_drive_file_id'])->toBeNull();

        $googleDriveOnlyData = collect($pageData)->firstWhere('id', $googleDriveOnly->id);
        expect($googleDriveOnlyData['file_path'])->toBe('');
        expect($googleDriveOnlyData['google_drive_file_id'])->not->toBeNull();

        $bothData = collect($pageData)->firstWhere('id', $both->id);
        expect($bothData['file_path'])->not->toBeNull();
        expect($bothData['google_drive_file_id'])->not->toBeNull();
    });
});
