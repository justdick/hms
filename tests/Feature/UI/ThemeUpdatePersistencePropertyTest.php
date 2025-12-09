<?php

/**
 * Property-Based Tests for Theme Update Persistence
 *
 * These tests verify the correctness properties of theme update persistence
 * as defined in the design document.
 *
 * **Feature: ui-theming-system, Property 2: Theme Update Persistence**
 * **Validates: Requirements 6.2, 9.4**
 */

use App\Models\ThemeSetting;
use App\Models\User;
use App\Services\ThemeSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Property 2: Theme Update Persistence
 *
 * *For any* valid theme configuration update, after saving, fetching the theme
 * should return the updated values.
 */
describe('Property 2: Theme Update Persistence', function () {
    beforeEach(function () {
        $this->service = new ThemeSettingService;

        // Create admin user with theme management permission
        $this->admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => 'settings.manage-theme',
            'guard_name' => 'web',
        ]);
        $this->admin->givePermissionTo($permission);
    });

    it('persists color updates and retrieves them correctly', function () {
        $newColors = [
            'primary' => '220 85% 50%',
            'success' => '150 75% 40%',
        ];

        $this->service->updateColors($newColors);
        $theme = $this->service->getTheme();

        expect($theme['colors']['primary'])->toBe('220 85% 50%');
        expect($theme['colors']['success'])->toBe('150 75% 40%');
    });

    it('persists branding updates and retrieves them correctly', function () {
        $newBranding = [
            'hospitalName' => 'Test Hospital',
            'logoUrl' => '/storage/logos/test.png',
        ];

        $this->service->updateBranding($newBranding);
        $theme = $this->service->getTheme();

        expect($theme['branding']['hospitalName'])->toBe('Test Hospital');
        expect($theme['branding']['logoUrl'])->toBe('/storage/logos/test.png');
    });

    it('merges partial color updates with existing values', function () {
        // First update
        $this->service->updateColors(['primary' => '200 80% 45%']);

        // Second partial update
        $this->service->updateColors(['success' => '140 70% 50%']);

        $theme = $this->service->getTheme();

        // Both should be present
        expect($theme['colors']['primary'])->toBe('200 80% 45%');
        expect($theme['colors']['success'])->toBe('140 70% 50%');
    });

    it('persists theme updates via API endpoint', function () {
        $response = $this->actingAs($this->admin)->putJson('/api/settings/theme', [
            'colors' => [
                'primary' => '230 90% 55%',
            ],
            'branding' => [
                'hospitalName' => 'API Test Hospital',
            ],
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.colors.primary', '230 90% 55%');
        $response->assertJsonPath('data.branding.hospitalName', 'API Test Hospital');

        // Verify persistence by fetching again
        $getResponse = $this->actingAs($this->admin)->getJson('/api/settings/theme');
        $getResponse->assertOk();
        $getResponse->assertJsonPath('data.colors.primary', '230 90% 55%');
        $getResponse->assertJsonPath('data.branding.hospitalName', 'API Test Hospital');
    });

    it('rejects invalid color formats via API', function () {
        $response = $this->actingAs($this->admin)->putJson('/api/settings/theme', [
            'colors' => [
                'primary' => 'invalid-color',
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['colors.primary']);
    });

    it('requires authentication for theme updates', function () {
        $response = $this->putJson('/api/settings/theme', [
            'colors' => [
                'primary' => '210 90% 45%',
            ],
        ]);

        // Routes within auth middleware redirect to login or return 403
        $response->assertStatus(403);
    });

    it('requires permission for theme updates', function () {
        $userWithoutPermission = User::factory()->create();

        $response = $this->actingAs($userWithoutPermission)->putJson('/api/settings/theme', [
            'colors' => [
                'primary' => '210 90% 45%',
            ],
        ]);

        $response->assertForbidden();
    });
});

/**
 * Property-based tests using random valid color generation
 */
describe('Property 2: Theme Update Persistence - Property-Based', function () {
    beforeEach(function () {
        $this->service = new ThemeSettingService;

        $this->admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => 'settings.manage-theme',
            'guard_name' => 'web',
        ]);
        $this->admin->givePermissionTo($permission);
    });

    it('persists any valid randomly generated color configuration', function () {
        // Generate 50 random valid theme configurations
        for ($i = 0; $i < 50; $i++) {
            // Clear previous settings
            ThemeSetting::truncate();

            $randomColors = [
                'primary' => generateRandomHslColor(),
                'success' => generateRandomHslColor(),
                'warning' => generateRandomHslColor(),
                'error' => generateRandomHslColor(),
            ];

            $this->service->updateColors($randomColors);
            $theme = $this->service->getTheme();

            foreach ($randomColors as $key => $expectedValue) {
                expect($theme['colors'][$key])->toBe($expectedValue,
                    "Color '$key' should persist value '$expectedValue' but got '{$theme['colors'][$key]}'"
                );
            }
        }
    });

    it('persists any valid randomly generated branding configuration', function () {
        for ($i = 0; $i < 50; $i++) {
            ThemeSetting::truncate();

            $randomBranding = [
                'hospitalName' => fake()->company().' Hospital',
                'logoUrl' => '/storage/logos/'.fake()->uuid().'.png',
            ];

            $this->service->updateBranding($randomBranding);
            $theme = $this->service->getTheme();

            expect($theme['branding']['hospitalName'])->toBe($randomBranding['hospitalName']);
            expect($theme['branding']['logoUrl'])->toBe($randomBranding['logoUrl']);
        }
    });

    it('round-trips theme configuration through API', function () {
        for ($i = 0; $i < 20; $i++) {
            ThemeSetting::truncate();

            $randomConfig = [
                'colors' => [
                    'primary' => generateRandomHslColor(),
                    'accent' => generateRandomHslColor(),
                ],
                'branding' => [
                    'hospitalName' => fake()->company().' Medical Center',
                ],
            ];

            // Update via API
            $updateResponse = $this->actingAs($this->admin)->putJson('/api/settings/theme', $randomConfig);
            $updateResponse->assertOk();

            // Fetch via API
            $getResponse = $this->actingAs($this->admin)->getJson('/api/settings/theme');
            $getResponse->assertOk();

            $fetchedTheme = $getResponse->json('data');

            // Verify round-trip
            expect($fetchedTheme['colors']['primary'])->toBe($randomConfig['colors']['primary']);
            expect($fetchedTheme['colors']['accent'])->toBe($randomConfig['colors']['accent']);
            expect($fetchedTheme['branding']['hospitalName'])->toBe($randomConfig['branding']['hospitalName']);
        }
    });
});

/**
 * Helper function to generate random valid HSL color
 */
function generateRandomHslColor(): string
{
    $hue = rand(0, 360);
    $saturation = rand(0, 100);
    $lightness = rand(0, 100);

    return "$hue {$saturation}% {$lightness}%";
}
