<?php

/**
 * Property-Based Tests for Logo Upload Validation
 *
 * These tests verify the correctness properties of logo upload validation
 * as defined in the design document.
 *
 * **Feature: ui-theming-system, Property 5: Logo Upload Validation**
 * **Validates: Requirements 9.3**
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * Property 5: Logo Upload Validation
 *
 * *For any* uploaded logo file, the system should validate file type (PNG, JPG, SVG)
 * and reject invalid formats with an appropriate error message.
 */
describe('Property 5: Logo Upload Validation', function () {
    beforeEach(function () {
        Storage::fake('public');

        $this->admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => 'settings.manage-theme',
            'guard_name' => 'web',
        ]);
        $this->admin->givePermissionTo($permission);
    });

    it('accepts PNG file uploads', function () {
        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->actingAs($this->admin)->postJson('/api/settings/theme/logo', [
            'logo' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['logoUrl'], 'message']);
        expect($response->json('data.logoUrl'))->toContain('/storage/logos/');
    });

    it('accepts JPG file uploads', function () {
        $file = UploadedFile::fake()->image('logo.jpg', 200, 200);

        $response = $this->actingAs($this->admin)->postJson('/api/settings/theme/logo', [
            'logo' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['logoUrl'], 'message']);
    });

    it('accepts JPEG file uploads', function () {
        $file = UploadedFile::fake()->image('logo.jpeg', 200, 200);

        $response = $this->actingAs($this->admin)->postJson('/api/settings/theme/logo', [
            'logo' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['logoUrl'], 'message']);
    });

    it('accepts SVG file uploads', function () {
        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><circle cx="50" cy="50" r="40"/></svg>';
        $file = UploadedFile::fake()->createWithContent('logo.svg', $svgContent);

        $response = $this->actingAs($this->admin)->postJson('/api/settings/theme/logo', [
            'logo' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['logoUrl'], 'message']);
    });

    it('rejects GIF file uploads', function () {
        $file = UploadedFile::fake()->image('logo.gif', 200, 200);

        $response = $this->actingAs($this->admin)->postJson('/api/settings/theme/logo', [
            'logo' => $file,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['logo']);
    });

    it('rejects BMP file uploads', function () {
        $file = UploadedFile::fake()->create('logo.bmp', 100, 'image/bmp');

        $response = $this->actingAs($this->admin)->postJson('/api/settings/theme/logo', [
            'logo' => $file,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['logo']);
    });

    it('rejects WEBP file uploads', function () {
        $file = UploadedFile::fake()->create('logo.webp', 100, 'image/webp');

        $response = $this->actingAs($this->admin)->postJson('/api/settings/theme/logo', [
            'logo' => $file,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['logo']);
    });

    it('rejects PDF file uploads', function () {
        $file = UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->admin)->postJson('/api/settings/theme/logo', [
            'logo' => $file,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['logo']);
    });

    it('rejects files exceeding 2MB size limit', function () {
        // Create a file larger than 2MB (2048KB)
        $file = UploadedFile::fake()->image('logo.png')->size(2049);

        $response = $this->actingAs($this->admin)->postJson('/api/settings/theme/logo', [
            'logo' => $file,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['logo']);
    });

    it('accepts files at exactly 2MB size limit', function () {
        // Create a file at exactly 2MB (2048KB)
        $file = UploadedFile::fake()->image('logo.png')->size(2048);

        $response = $this->actingAs($this->admin)->postJson('/api/settings/theme/logo', [
            'logo' => $file,
        ]);

        $response->assertOk();
    });

    it('rejects requests without a file', function () {
        $response = $this->actingAs($this->admin)->postJson('/api/settings/theme/logo', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['logo']);
    });

    it('requires authentication for logo upload', function () {
        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->postJson('/api/settings/theme/logo', [
            'logo' => $file,
        ]);

        // Routes within auth middleware return 403 for unauthenticated requests
        $response->assertStatus(403);
    });

    it('requires permission for logo upload', function () {
        $userWithoutPermission = User::factory()->create();
        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->actingAs($userWithoutPermission)->postJson('/api/settings/theme/logo', [
            'logo' => $file,
        ]);

        $response->assertForbidden();
    });

    it('stores uploaded logo in public storage', function () {
        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->actingAs($this->admin)->postJson('/api/settings/theme/logo', [
            'logo' => $file,
        ]);

        $response->assertOk();

        $logoUrl = $response->json('data.logoUrl');
        $storagePath = str_replace('/storage/', '', $logoUrl);

        Storage::disk('public')->assertExists($storagePath);
    });

    it('updates branding with new logo URL', function () {
        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->actingAs($this->admin)->postJson('/api/settings/theme/logo', [
            'logo' => $file,
        ]);

        $response->assertOk();

        // Verify the theme branding was updated
        $themeResponse = $this->actingAs($this->admin)->getJson('/api/settings/theme');
        $themeResponse->assertOk();

        $logoUrl = $response->json('data.logoUrl');
        expect($themeResponse->json('data.branding.logoUrl'))->toBe($logoUrl);
    });
});

/**
 * Property-based tests using random file generation
 */
describe('Property 5: Logo Upload Validation - Property-Based', function () {
    beforeEach(function () {
        Storage::fake('public');

        $this->admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => 'settings.manage-theme',
            'guard_name' => 'web',
        ]);
        $this->admin->givePermissionTo($permission);
    });

    it('accepts any valid image format within size limit', function () {
        $validFormats = ['png', 'jpg', 'jpeg'];

        foreach ($validFormats as $format) {
            for ($i = 0; $i < 10; $i++) {
                Storage::fake('public');

                $width = rand(32, 512);
                $height = rand(32, 512);
                $size = rand(1, 2048); // Up to 2MB

                $file = UploadedFile::fake()->image("logo_{$i}.{$format}", $width, $height)->size($size);

                $response = $this->actingAs($this->admin)->postJson('/api/settings/theme/logo', [
                    'logo' => $file,
                ]);

                $response->assertOk();
            }
        }
    });

    it('rejects any invalid file format', function () {
        $invalidFormats = ['gif', 'bmp', 'webp', 'tiff', 'ico', 'pdf', 'doc', 'txt', 'exe'];

        foreach ($invalidFormats as $format) {
            $file = UploadedFile::fake()->create("logo.{$format}", 100);

            $response = $this->actingAs($this->admin)->postJson('/api/settings/theme/logo', [
                'logo' => $file,
            ]);

            $response->assertUnprocessable();
            $response->assertJsonValidationErrors(['logo']);
        }
    });

    it('rejects any file exceeding size limit regardless of format', function () {
        $validFormats = ['png', 'jpg', 'jpeg'];

        foreach ($validFormats as $format) {
            for ($i = 0; $i < 5; $i++) {
                $oversizedKb = rand(2049, 5000); // Over 2MB

                $file = UploadedFile::fake()->image("logo.{$format}")->size($oversizedKb);

                $response = $this->actingAs($this->admin)->postJson('/api/settings/theme/logo', [
                    'logo' => $file,
                ]);

                $response->assertUnprocessable();
                $response->assertJsonValidationErrors(['logo']);
            }
        }
    });
});
