<?php

/**
 * Property-Based Tests for Theme Reset
 *
 * These tests verify the correctness properties of theme reset functionality
 * as defined in the design document.
 *
 * **Feature: ui-theming-system, Property 6: Theme Reset Restores Defaults**
 * **Validates: Requirements 9.5**
 */

use App\Models\ThemeSetting;
use App\Models\User;
use App\Services\ThemeSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Property 6: Theme Reset Restores Defaults
 *
 * *For any* customized theme, after reset, all theme values should match
 * the default healthcare theme configuration.
 */
describe('Property 6: Theme Reset Restores Defaults', function () {
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

    it('resets colors to default values after customization', function () {
        // Customize colors
        $customColors = [
            'primary' => '300 80% 60%',
            'success' => '100 50% 30%',
            'warning' => '50 90% 70%',
        ];
        $this->service->updateColors($customColors);

        // Verify customization was applied
        $customizedTheme = $this->service->getTheme();
        expect($customizedTheme['colors']['primary'])->toBe('300 80% 60%');

        // Reset theme
        $resetTheme = $this->service->resetTheme();

        // Verify all colors match defaults
        $defaults = ThemeSettingService::DEFAULT_THEME;
        foreach ($defaults['colors'] as $key => $defaultValue) {
            expect($resetTheme['colors'][$key])->toBe($defaultValue,
                "Color '$key' should be reset to default '$defaultValue'"
            );
        }
    });

    it('resets branding to default values after customization', function () {
        // Customize branding
        $this->service->updateBranding([
            'hospitalName' => 'Custom Hospital Name',
            'logoUrl' => '/storage/logos/custom-logo.png',
        ]);

        // Verify customization was applied
        $customizedTheme = $this->service->getTheme();
        expect($customizedTheme['branding']['hospitalName'])->toBe('Custom Hospital Name');

        // Reset theme
        $resetTheme = $this->service->resetTheme();

        // Verify branding matches defaults
        $defaults = ThemeSettingService::DEFAULT_THEME;
        expect($resetTheme['branding']['hospitalName'])->toBe($defaults['branding']['hospitalName']);
        expect($resetTheme['branding']['logoUrl'])->toBe($defaults['branding']['logoUrl']);
    });

    it('resets theme via API endpoint', function () {
        // First customize the theme
        $this->actingAs($this->admin)->putJson('/api/settings/theme', [
            'colors' => ['primary' => '180 70% 40%'],
            'branding' => ['hospitalName' => 'API Custom Hospital'],
        ]);

        // Reset via API
        $response = $this->actingAs($this->admin)->postJson('/api/settings/theme/reset');

        $response->assertOk();
        $response->assertJsonPath('message', 'Theme settings reset to defaults.');

        // Verify reset values match defaults
        $defaults = ThemeSettingService::DEFAULT_THEME;
        $response->assertJsonPath('data.colors.primary', $defaults['colors']['primary']);
        $response->assertJsonPath('data.branding.hospitalName', $defaults['branding']['hospitalName']);
    });

    it('requires permission to reset theme', function () {
        $userWithoutPermission = User::factory()->create();

        $response = $this->actingAs($userWithoutPermission)->postJson('/api/settings/theme/reset');

        $response->assertForbidden();
    });

    it('persists reset values in database', function () {
        // Customize theme
        $this->service->updateColors(['primary' => '270 60% 50%']);
        $this->service->updateBranding(['hospitalName' => 'Persisted Custom']);

        // Reset
        $this->service->resetTheme();

        // Fetch fresh from database
        $freshTheme = $this->service->getTheme();
        $defaults = ThemeSettingService::DEFAULT_THEME;

        expect($freshTheme['colors']['primary'])->toBe($defaults['colors']['primary']);
        expect($freshTheme['branding']['hospitalName'])->toBe($defaults['branding']['hospitalName']);
    });
});

/**
 * Property-based tests using random customizations
 */
describe('Property 6: Theme Reset - Property-Based', function () {
    beforeEach(function () {
        $this->service = new ThemeSettingService;

        $this->admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => 'settings.manage-theme',
            'guard_name' => 'web',
        ]);
        $this->admin->givePermissionTo($permission);
    });

    it('resets any randomly customized theme to exact defaults', function () {
        $defaults = ThemeSettingService::DEFAULT_THEME;

        // Run 30 iterations with random customizations
        for ($i = 0; $i < 30; $i++) {
            ThemeSetting::truncate();

            // Apply random customizations
            $randomColors = [];
            foreach (array_keys($defaults['colors']) as $colorKey) {
                $randomColors[$colorKey] = generateRandomHslColorForReset();
            }
            $this->service->updateColors($randomColors);

            $randomBranding = [
                'hospitalName' => fake()->company().' '.fake()->randomElement(['Hospital', 'Medical Center', 'Clinic']),
                'logoUrl' => '/storage/logos/'.fake()->uuid().'.'.fake()->randomElement(['png', 'jpg', 'svg']),
            ];
            $this->service->updateBranding($randomBranding);

            // Verify customization was applied
            $customized = $this->service->getTheme();
            expect($customized['colors']['primary'])->not->toBe($defaults['colors']['primary']);

            // Reset theme
            $resetTheme = $this->service->resetTheme();

            // Verify ALL values match defaults exactly
            foreach ($defaults['colors'] as $key => $defaultValue) {
                expect($resetTheme['colors'][$key])->toBe($defaultValue,
                    "Iteration $i: Color '$key' should be '$defaultValue' after reset"
                );
            }

            foreach ($defaults['branding'] as $key => $defaultValue) {
                expect($resetTheme['branding'][$key])->toBe($defaultValue,
                    "Iteration $i: Branding '$key' should be '$defaultValue' after reset"
                );
            }
        }
    });

    it('reset is idempotent - multiple resets produce same result', function () {
        $defaults = ThemeSettingService::DEFAULT_THEME;

        // Customize
        $this->service->updateColors(['primary' => '45 80% 55%']);

        // Reset multiple times
        $firstReset = $this->service->resetTheme();
        $secondReset = $this->service->resetTheme();
        $thirdReset = $this->service->resetTheme();

        // All resets should produce identical results
        expect($firstReset)->toBe($secondReset);
        expect($secondReset)->toBe($thirdReset);
        expect($thirdReset)->toBe($defaults);
    });

    it('reset via API with random customizations restores defaults', function () {
        $defaults = ThemeSettingService::DEFAULT_THEME;

        for ($i = 0; $i < 10; $i++) {
            ThemeSetting::truncate();

            // Apply random customization via API
            $this->actingAs($this->admin)->putJson('/api/settings/theme', [
                'colors' => [
                    'primary' => generateRandomHslColorForReset(),
                    'accent' => generateRandomHslColorForReset(),
                    'success' => generateRandomHslColorForReset(),
                ],
                'branding' => [
                    'hospitalName' => fake()->company().' Hospital',
                ],
            ]);

            // Reset via API
            $response = $this->actingAs($this->admin)->postJson('/api/settings/theme/reset');
            $response->assertOk();

            $resetData = $response->json('data');

            // Verify all colors match defaults
            foreach ($defaults['colors'] as $key => $defaultValue) {
                expect($resetData['colors'][$key])->toBe($defaultValue,
                    "API Reset iteration $i: Color '$key' should be '$defaultValue'"
                );
            }

            // Verify branding matches defaults
            expect($resetData['branding']['hospitalName'])->toBe($defaults['branding']['hospitalName']);
            expect($resetData['branding']['logoUrl'])->toBe($defaults['branding']['logoUrl']);
        }
    });
});

/**
 * Helper function to generate random valid HSL color
 */
function generateRandomHslColorForReset(): string
{
    $hue = rand(0, 360);
    $saturation = rand(0, 100);
    $lightness = rand(0, 100);

    return "$hue {$saturation}% {$lightness}%";
}
