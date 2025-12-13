<?php

namespace App\Services;

use App\Models\ThemeSetting;

class ThemeSettingService
{
    /**
     * Default theme configuration.
     */
    public const DEFAULT_THEME = [
        'colors' => [
            'primary' => '210 90% 45%',
            'primaryForeground' => '0 0% 100%',
            'secondary' => '210 20% 96%',
            'secondaryForeground' => '210 40% 20%',
            'accent' => '180 60% 45%',
            'accentForeground' => '0 0% 100%',
            'success' => '142 70% 45%',
            'warning' => '38 92% 50%',
            'error' => '0 84% 60%',
            'info' => '210 100% 50%',
            // Sidebar colors
            'sidebar' => '210 20% 98%',
            'sidebarForeground' => '210 40% 20%',
            'sidebarPrimary' => '210 90% 45%',
            'sidebarPrimaryForeground' => '0 0% 100%',
            'sidebarAccent' => '210 30% 94%',
            'sidebarAccentForeground' => '210 40% 25%',
        ],
        'branding' => [
            'logoUrl' => null,
            'hospitalName' => 'Hospital Management System',
        ],
    ];

    /**
     * Get the complete theme configuration.
     */
    public function getTheme(): array
    {
        $colors = ThemeSetting::getValue('colors', self::DEFAULT_THEME['colors']);
        $branding = ThemeSetting::getValue('branding', self::DEFAULT_THEME['branding']);

        return [
            'colors' => array_merge(self::DEFAULT_THEME['colors'], $colors ?? []),
            'branding' => array_merge(self::DEFAULT_THEME['branding'], $branding ?? []),
        ];
    }

    /**
     * Update theme colors.
     */
    public function updateColors(array $colors): array
    {
        $validatedColors = $this->validateColors($colors);
        $currentColors = ThemeSetting::getValue('colors', self::DEFAULT_THEME['colors']);
        $mergedColors = array_merge($currentColors ?? [], $validatedColors);

        ThemeSetting::setValue('colors', $mergedColors);

        return $mergedColors;
    }

    /**
     * Update branding settings.
     */
    public function updateBranding(array $branding): array
    {
        $currentBranding = ThemeSetting::getValue('branding', self::DEFAULT_THEME['branding']);
        $mergedBranding = array_merge($currentBranding ?? [], $branding);

        ThemeSetting::setValue('branding', $mergedBranding);

        return $mergedBranding;
    }

    /**
     * Reset theme to defaults.
     */
    public function resetTheme(): array
    {
        ThemeSetting::setValue('colors', self::DEFAULT_THEME['colors']);
        ThemeSetting::setValue('branding', self::DEFAULT_THEME['branding']);

        return self::DEFAULT_THEME;
    }

    /**
     * Validate color values are in HSL format.
     *
     * @throws \InvalidArgumentException
     */
    public function validateColors(array $colors): array
    {
        $validated = [];
        $validKeys = array_keys(self::DEFAULT_THEME['colors']);

        foreach ($colors as $key => $value) {
            if (! in_array($key, $validKeys)) {
                continue;
            }

            if (! $this->isValidHslColor($value)) {
                throw new \InvalidArgumentException(
                    "Invalid HSL color format for '{$key}': {$value}. Expected format: 'H S% L%' (e.g., '210 90% 45%')"
                );
            }

            $validated[$key] = $value;
        }

        return $validated;
    }

    /**
     * Check if a color value is in valid HSL format.
     */
    public function isValidHslColor(string $value): bool
    {
        // HSL format: "H S% L%" where H is 0-360, S and L are 0-100%
        $pattern = '/^\d{1,3}\s+\d{1,3}%\s+\d{1,3}%$/';

        if (! preg_match($pattern, $value)) {
            return false;
        }

        // Extract values and validate ranges
        preg_match('/^(\d{1,3})\s+(\d{1,3})%\s+(\d{1,3})%$/', $value, $matches);

        if (count($matches) !== 4) {
            return false;
        }

        $hue = (int) $matches[1];
        $saturation = (int) $matches[2];
        $lightness = (int) $matches[3];

        return $hue >= 0 && $hue <= 360
            && $saturation >= 0 && $saturation <= 100
            && $lightness >= 0 && $lightness <= 100;
    }

    /**
     * Get theme with graceful fallback for corrupted data.
     */
    public function getThemeWithFallback(): array
    {
        try {
            $theme = $this->getTheme();

            // Validate the retrieved theme
            if (! is_array($theme['colors']) || ! is_array($theme['branding'])) {
                return self::DEFAULT_THEME;
            }

            return $theme;
        } catch (\Throwable $e) {
            // Log the error and return defaults
            report($e);

            return self::DEFAULT_THEME;
        }
    }
}
