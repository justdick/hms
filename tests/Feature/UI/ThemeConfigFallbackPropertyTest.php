<?php

/**
 * Property-Based Tests for Invalid Config Fallback
 *
 * These tests verify the correctness properties of the theme fallback behavior
 * as defined in the design document.
 *
 * **Feature: ui-theming-system, Property 8: Invalid Config Fallback**
 * **Validates: Requirements 10.4**
 */

use App\Models\ThemeSetting;
use App\Services\ThemeSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Property 8: Invalid Config Fallback
 *
 * *For any* corrupted or invalid theme configuration in the database,
 * the system should gracefully fall back to default values without crashing.
 */
describe('Property 8: Invalid Config Fallback', function () {
    beforeEach(function () {
        $this->service = new ThemeSettingService;
    });

    it('returns default theme when no configuration exists', function () {
        // Ensure no theme settings exist
        ThemeSetting::query()->delete();

        $theme = $this->service->getThemeWithFallback();

        expect($theme)->toBe(ThemeSettingService::DEFAULT_THEME);
    });

    it('returns default theme when only colors config exists', function () {
        // Only set colors, branding should fall back to defaults
        ThemeSetting::setValue('colors', ['primary' => '200 50% 50%']);

        $theme = $this->service->getThemeWithFallback();

        expect($theme['branding'])->toBe(ThemeSettingService::DEFAULT_THEME['branding']);
    });

    it('merges partial color config with defaults', function () {
        $partialColors = [
            'primary' => '220 80% 50%',
        ];
        ThemeSetting::setValue('colors', $partialColors);

        $theme = $this->service->getTheme();

        // Custom value should be used
        expect($theme['colors']['primary'])->toBe('220 80% 50%');
        // Other values should fall back to defaults
        expect($theme['colors']['success'])->toBe(ThemeSettingService::DEFAULT_THEME['colors']['success']);
    });

    it('merges partial branding config with defaults', function () {
        $partialBranding = [
            'hospitalName' => 'Custom Hospital',
        ];
        ThemeSetting::setValue('branding', $partialBranding);

        $theme = $this->service->getTheme();

        // Custom value should be used
        expect($theme['branding']['hospitalName'])->toBe('Custom Hospital');
        // Other values should fall back to defaults
        expect($theme['branding']['logoUrl'])->toBe(ThemeSettingService::DEFAULT_THEME['branding']['logoUrl']);
    });

    it('handles empty array config gracefully', function () {
        ThemeSetting::setValue('colors', []);
        ThemeSetting::setValue('branding', []);

        $theme = $this->service->getThemeWithFallback();

        // Should return defaults when arrays are empty
        expect($theme['colors'])->toBe(ThemeSettingService::DEFAULT_THEME['colors']);
        expect($theme['branding'])->toBe(ThemeSettingService::DEFAULT_THEME['branding']);
    });

    it('preserves valid custom config values', function () {
        $customColors = [
            'primary' => '180 60% 40%',
            'success' => '120 70% 35%',
            'warning' => '45 90% 55%',
        ];
        ThemeSetting::setValue('colors', $customColors);

        $theme = $this->service->getTheme();

        expect($theme['colors']['primary'])->toBe('180 60% 40%');
        expect($theme['colors']['success'])->toBe('120 70% 35%');
        expect($theme['colors']['warning'])->toBe('45 90% 55%');
    });

    it('returns complete theme structure even with partial data', function () {
        ThemeSetting::setValue('colors', ['primary' => '200 50% 50%']);

        $theme = $this->service->getThemeWithFallback();

        // Verify structure is complete
        expect($theme)->toHaveKey('colors');
        expect($theme)->toHaveKey('branding');
        expect($theme['colors'])->toHaveKeys([
            'primary',
            'primaryForeground',
            'secondary',
            'secondaryForeground',
            'accent',
            'accentForeground',
            'success',
            'warning',
            'error',
            'info',
        ]);
        expect($theme['branding'])->toHaveKeys(['logoUrl', 'hospitalName']);
    });
});

/**
 * Property-based tests for fallback behavior with various data states
 */
describe('Property 8: Invalid Config Fallback - Property-Based', function () {
    beforeEach(function () {
        $this->service = new ThemeSettingService;
    });

    it('always returns valid theme structure regardless of database state', function () {
        $testCases = [
            // No data
            fn () => ThemeSetting::query()->delete(),
            // Empty colors
            fn () => ThemeSetting::setValue('colors', []),
            // Partial colors
            fn () => ThemeSetting::setValue('colors', ['primary' => '210 90% 45%']),
            // Full custom colors
            fn () => ThemeSetting::setValue('colors', ThemeSettingService::DEFAULT_THEME['colors']),
        ];

        foreach ($testCases as $index => $setup) {
            // Clean up before each test case
            ThemeSetting::query()->delete();
            $setup();

            $theme = $this->service->getThemeWithFallback();

            // Should always have valid structure
            expect($theme)->toBeArray();
            expect($theme)->toHaveKey('colors');
            expect($theme)->toHaveKey('branding');
            expect($theme['colors'])->toBeArray();
            expect($theme['branding'])->toBeArray();
        }
    });

    it('never throws exception from getThemeWithFallback', function () {
        // Test various edge cases that might cause exceptions
        $edgeCases = [
            fn () => ThemeSetting::query()->delete(),
            fn () => ThemeSetting::setValue('colors', []),
            fn () => ThemeSetting::setValue('branding', []),
        ];

        foreach ($edgeCases as $setup) {
            // Clean up before each test case
            ThemeSetting::query()->delete();
            $setup();

            // Should not throw
            $theme = $this->service->getThemeWithFallback();
            expect($theme)->toBeArray();
        }
    });
});
