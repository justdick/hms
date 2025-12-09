<?php

/**
 * Property-Based Tests for Theme Validation
 *
 * These tests verify the correctness properties of the theme validation
 * as defined in the design document.
 *
 * **Feature: ui-theming-system, Property 3: Theme Validation**
 * **Validates: Requirements 6.4**
 */

use App\Services\ThemeSettingService;

/**
 * Property 3: Theme Validation
 *
 * *For any* theme configuration with invalid color values (non-HSL format, out of range),
 * the system should reject the update and return a validation error.
 */
describe('Property 3: Theme Validation', function () {
    beforeEach(function () {
        $this->service = new ThemeSettingService;
    });

    it('accepts valid HSL color format', function () {
        $validColors = [
            '210 90% 45%',
            '0 0% 0%',
            '360 100% 100%',
            '180 50% 50%',
        ];

        foreach ($validColors as $color) {
            expect($this->service->isValidHslColor($color))->toBeTrue(
                "Color '$color' should be valid"
            );
        }
    });

    it('rejects invalid HSL color formats', function () {
        $invalidColors = [
            'rgb(255, 0, 0)',      // RGB format
            '#ff0000',             // Hex format
            'red',                 // Named color
            '210, 90%, 45%',       // Comma-separated
            '210 90 45',           // Missing percent signs
            '210 90% 45',          // Missing last percent
            '210 90 45%',          // Missing middle percent
            '',                    // Empty string
            '   ',                 // Whitespace only
            'invalid',             // Random string
        ];

        foreach ($invalidColors as $color) {
            expect($this->service->isValidHslColor($color))->toBeFalse(
                "Color '$color' should be invalid"
            );
        }
    });

    it('rejects HSL colors with out-of-range hue values', function () {
        $invalidHueColors = [
            '361 50% 50%',         // Hue > 360
            '400 50% 50%',         // Hue way out of range
            '999 50% 50%',         // Hue extremely out of range
        ];

        foreach ($invalidHueColors as $color) {
            expect($this->service->isValidHslColor($color))->toBeFalse(
                "Color '$color' with invalid hue should be rejected"
            );
        }
    });

    it('rejects HSL colors with out-of-range saturation values', function () {
        $invalidSaturationColors = [
            '210 101% 50%',        // Saturation > 100
            '210 150% 50%',        // Saturation way out of range
        ];

        foreach ($invalidSaturationColors as $color) {
            expect($this->service->isValidHslColor($color))->toBeFalse(
                "Color '$color' with invalid saturation should be rejected"
            );
        }
    });

    it('rejects HSL colors with out-of-range lightness values', function () {
        $invalidLightnessColors = [
            '210 50% 101%',        // Lightness > 100
            '210 50% 150%',        // Lightness way out of range
        ];

        foreach ($invalidLightnessColors as $color) {
            expect($this->service->isValidHslColor($color))->toBeFalse(
                "Color '$color' with invalid lightness should be rejected"
            );
        }
    });

    it('throws exception when validating colors with invalid format', function () {
        $invalidColors = [
            'primary' => 'invalid-color',
        ];

        expect(fn () => $this->service->validateColors($invalidColors))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('returns validated colors for valid input', function () {
        $validColors = [
            'primary' => '210 90% 45%',
            'success' => '142 70% 45%',
        ];

        $result = $this->service->validateColors($validColors);

        expect($result)->toBe($validColors);
    });

    it('filters out unknown color keys during validation', function () {
        $colorsWithUnknown = [
            'primary' => '210 90% 45%',
            'unknownKey' => '180 50% 50%',
        ];

        $result = $this->service->validateColors($colorsWithUnknown);

        expect($result)->toHaveKey('primary');
        expect($result)->not->toHaveKey('unknownKey');
    });

    it('validates all default theme color keys', function () {
        $defaultColors = ThemeSettingService::DEFAULT_THEME['colors'];

        foreach ($defaultColors as $key => $value) {
            expect($this->service->isValidHslColor($value))->toBeTrue(
                "Default color '$key' with value '$value' should be valid"
            );
        }
    });
});

/**
 * Property-based tests using random valid/invalid color generation
 */
describe('Property 3: Theme Validation - Property-Based', function () {
    beforeEach(function () {
        $this->service = new ThemeSettingService;
    });

    it('accepts any valid HSL color within range', function () {
        // Generate random valid HSL colors
        for ($i = 0; $i < 100; $i++) {
            $hue = rand(0, 360);
            $saturation = rand(0, 100);
            $lightness = rand(0, 100);
            $color = "$hue {$saturation}% {$lightness}%";

            expect($this->service->isValidHslColor($color))->toBeTrue(
                "Randomly generated valid color '$color' should be accepted"
            );
        }
    });

    it('rejects any HSL color with out-of-range values', function () {
        // Generate random invalid HSL colors
        $invalidGenerators = [
            // Invalid hue (> 360)
            fn () => (rand(361, 999)).' '.rand(0, 100).'% '.rand(0, 100).'%',
            // Invalid saturation (> 100)
            fn () => rand(0, 360).' '.rand(101, 200).'% '.rand(0, 100).'%',
            // Invalid lightness (> 100)
            fn () => rand(0, 360).' '.rand(0, 100).'% '.rand(101, 200).'%',
        ];

        foreach ($invalidGenerators as $generator) {
            for ($i = 0; $i < 30; $i++) {
                $color = $generator();
                expect($this->service->isValidHslColor($color))->toBeFalse(
                    "Randomly generated invalid color '$color' should be rejected"
                );
            }
        }
    });
});
