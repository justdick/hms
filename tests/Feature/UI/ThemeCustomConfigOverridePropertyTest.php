<?php

/**
 * Property-Based Tests for Custom Config Override
 *
 * These tests verify the correctness properties of custom theme configuration
 * override behavior as defined in the design document.
 *
 * **Feature: ui-theming-system, Property 7: Custom Config Override**
 * **Validates: Requirements 10.2**
 */

use App\Models\ThemeSetting;
use App\Models\User;
use App\Services\ThemeSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Property 7: Custom Config Override
 *
 * *For any* saved custom theme configuration, when the application loads,
 * custom values should override the corresponding default values while
 * preserving unset defaults.
 */
describe('Property 7: Custom Config Override', function () {
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

    it('overrides only specified colors while preserving defaults', function () {
        $defaults = ThemeSettingService::DEFAULT_THEME;

        // Update only primary color
        $this->service->updateColors(['primary' => '300 80% 60%']);

        $theme = $this->service->getTheme();

        // Primary should be overridden
        expect($theme['colors']['primary'])->toBe('300 80% 60%');

        // All other colors should remain as defaults
        foreach ($defaults['colors'] as $key => $defaultValue) {
            if ($key !== 'primary') {
                expect($theme['colors'][$key])->toBe($defaultValue,
                    "Color '$key' should remain default '$defaultValue'"
                );
            }
        }
    });

    it('overrides only specified branding while preserving defaults', function () {
        $defaults = ThemeSettingService::DEFAULT_THEME;

        // Update only hospital name
        $this->service->updateBranding(['hospitalName' => 'Custom Hospital']);

        $theme = $this->service->getTheme();

        // Hospital name should be overridden
        expect($theme['branding']['hospitalName'])->toBe('Custom Hospital');

        // Logo URL should remain as default (null)
        expect($theme['branding']['logoUrl'])->toBe($defaults['branding']['logoUrl']);
    });

    it('merges multiple partial updates correctly', function () {
        $defaults = ThemeSettingService::DEFAULT_THEME;

        // First update: primary color
        $this->service->updateColors(['primary' => '200 70% 50%']);

        // Second update: success color
        $this->service->updateColors(['success' => '120 60% 40%']);

        // Third update: branding
        $this->service->updateBranding(['hospitalName' => 'Merged Hospital']);

        $theme = $this->service->getTheme();

        // Both color updates should be present
        expect($theme['colors']['primary'])->toBe('200 70% 50%');
        expect($theme['colors']['success'])->toBe('120 60% 40%');

        // Branding update should be present
        expect($theme['branding']['hospitalName'])->toBe('Merged Hospital');

        // Other values should remain as defaults
        expect($theme['colors']['warning'])->toBe($defaults['colors']['warning']);
        expect($theme['colors']['error'])->toBe($defaults['colors']['error']);
        expect($theme['branding']['logoUrl'])->toBe($defaults['branding']['logoUrl']);
    });

    it('loads custom config on fresh service instance', function () {
        // Update theme
        $this->service->updateColors(['accent' => '180 75% 45%']);
        $this->service->updateBranding(['hospitalName' => 'Fresh Load Hospital']);

        // Create fresh service instance (simulating app reload)
        $freshService = new ThemeSettingService;
        $theme = $freshService->getTheme();

        // Custom values should be loaded
        expect($theme['colors']['accent'])->toBe('180 75% 45%');
        expect($theme['branding']['hospitalName'])->toBe('Fresh Load Hospital');
    });

    it('overrides via API while preserving unset defaults', function () {
        $defaults = ThemeSettingService::DEFAULT_THEME;

        // Update only specific values via API
        $response = $this->actingAs($this->admin)->putJson('/api/settings/theme', [
            'colors' => [
                'primary' => '240 85% 55%',
                'info' => '200 90% 50%',
            ],
        ]);

        $response->assertOk();

        // Fetch theme
        $getResponse = $this->actingAs($this->admin)->getJson('/api/settings/theme');
        $theme = $getResponse->json('data');

        // Specified colors should be overridden
        expect($theme['colors']['primary'])->toBe('240 85% 55%');
        expect($theme['colors']['info'])->toBe('200 90% 50%');

        // Unspecified colors should remain as defaults
        expect($theme['colors']['success'])->toBe($defaults['colors']['success']);
        expect($theme['colors']['warning'])->toBe($defaults['colors']['warning']);
        expect($theme['colors']['error'])->toBe($defaults['colors']['error']);
    });
});

/**
 * Property-based tests using random partial configurations
 */
describe('Property 7: Custom Config Override - Property-Based', function () {
    beforeEach(function () {
        $this->service = new ThemeSettingService;

        $this->admin = User::factory()->create();
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => 'settings.manage-theme',
            'guard_name' => 'web',
        ]);
        $this->admin->givePermissionTo($permission);
    });

    it('correctly merges any random subset of color overrides with defaults', function () {
        $defaults = ThemeSettingService::DEFAULT_THEME;
        $allColorKeys = array_keys($defaults['colors']);

        for ($i = 0; $i < 30; $i++) {
            ThemeSetting::truncate();

            // Randomly select a subset of colors to override (1 to 5 colors)
            $numToOverride = rand(1, 5);
            shuffle($allColorKeys);
            $keysToOverride = array_slice($allColorKeys, 0, $numToOverride);

            // Generate random values for selected keys
            $customColors = [];
            foreach ($keysToOverride as $key) {
                $customColors[$key] = generateRandomHslColorForOverride();
            }

            // Apply custom colors
            $this->service->updateColors($customColors);

            // Get merged theme
            $theme = $this->service->getTheme();

            // Verify overridden values
            foreach ($customColors as $key => $customValue) {
                expect($theme['colors'][$key])->toBe($customValue,
                    "Iteration $i: Custom color '$key' should be '$customValue'"
                );
            }

            // Verify non-overridden values remain as defaults
            foreach ($defaults['colors'] as $key => $defaultValue) {
                if (! isset($customColors[$key])) {
                    expect($theme['colors'][$key])->toBe($defaultValue,
                        "Iteration $i: Default color '$key' should remain '$defaultValue'"
                    );
                }
            }
        }
    });

    it('correctly merges any random branding overrides with defaults', function () {
        $defaults = ThemeSettingService::DEFAULT_THEME;

        for ($i = 0; $i < 20; $i++) {
            ThemeSetting::truncate();

            // Randomly decide which branding fields to override
            $overrideHospitalName = (bool) rand(0, 1);
            $overrideLogoUrl = (bool) rand(0, 1);

            $customBranding = [];
            if ($overrideHospitalName) {
                $customBranding['hospitalName'] = fake()->company().' Hospital';
            }
            if ($overrideLogoUrl) {
                $customBranding['logoUrl'] = '/storage/logos/'.fake()->uuid().'.png';
            }

            if (! empty($customBranding)) {
                $this->service->updateBranding($customBranding);
            }

            $theme = $this->service->getTheme();

            // Verify overridden values
            if ($overrideHospitalName) {
                expect($theme['branding']['hospitalName'])->toBe($customBranding['hospitalName']);
            } else {
                expect($theme['branding']['hospitalName'])->toBe($defaults['branding']['hospitalName']);
            }

            if ($overrideLogoUrl) {
                expect($theme['branding']['logoUrl'])->toBe($customBranding['logoUrl']);
            } else {
                expect($theme['branding']['logoUrl'])->toBe($defaults['branding']['logoUrl']);
            }
        }
    });

    it('preserves custom overrides across multiple service instantiations', function () {
        $defaults = ThemeSettingService::DEFAULT_THEME;

        for ($i = 0; $i < 10; $i++) {
            ThemeSetting::truncate();

            // Apply random customizations
            $customColors = [
                'primary' => generateRandomHslColorForOverride(),
                'accent' => generateRandomHslColorForOverride(),
            ];
            $customBranding = [
                'hospitalName' => fake()->company().' Medical',
            ];

            $this->service->updateColors($customColors);
            $this->service->updateBranding($customBranding);

            // Create multiple fresh service instances
            for ($j = 0; $j < 3; $j++) {
                $freshService = new ThemeSettingService;
                $theme = $freshService->getTheme();

                // Custom values should persist
                expect($theme['colors']['primary'])->toBe($customColors['primary'],
                    "Iteration $i.$j: Primary should persist"
                );
                expect($theme['colors']['accent'])->toBe($customColors['accent'],
                    "Iteration $i.$j: Accent should persist"
                );
                expect($theme['branding']['hospitalName'])->toBe($customBranding['hospitalName'],
                    "Iteration $i.$j: Hospital name should persist"
                );

                // Defaults should be preserved for non-overridden values
                expect($theme['colors']['success'])->toBe($defaults['colors']['success'],
                    "Iteration $i.$j: Success should remain default"
                );
            }
        }
    });

    it('API partial updates preserve existing custom values', function () {
        for ($i = 0; $i < 10; $i++) {
            ThemeSetting::truncate();

            // First update: set primary
            $firstPrimary = generateRandomHslColorForOverride();
            $this->actingAs($this->admin)->putJson('/api/settings/theme', [
                'colors' => ['primary' => $firstPrimary],
            ]);

            // Second update: set accent (should not affect primary)
            $secondAccent = generateRandomHslColorForOverride();
            $this->actingAs($this->admin)->putJson('/api/settings/theme', [
                'colors' => ['accent' => $secondAccent],
            ]);

            // Fetch and verify both are preserved
            $response = $this->actingAs($this->admin)->getJson('/api/settings/theme');
            $theme = $response->json('data');

            expect($theme['colors']['primary'])->toBe($firstPrimary,
                "Iteration $i: First primary update should be preserved"
            );
            expect($theme['colors']['accent'])->toBe($secondAccent,
                "Iteration $i: Second accent update should be applied"
            );
        }
    });
});

/**
 * Helper function to generate random valid HSL color
 */
function generateRandomHslColorForOverride(): string
{
    $hue = rand(0, 360);
    $saturation = rand(0, 100);
    $lightness = rand(0, 100);

    return "$hue {$saturation}% {$lightness}%";
}
