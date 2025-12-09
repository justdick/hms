<?php

namespace App\Http\Requests\Settings;

use App\Services\ThemeSettingService;
use Illuminate\Foundation\Http\FormRequest;

class StoreThemeSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage-theme') ?? false;
    }

    public function rules(): array
    {
        return [
            'colors' => ['sometimes', 'array'],
            'colors.primary' => ['sometimes', 'string', 'regex:/^\d{1,3}\s+\d{1,3}%\s+\d{1,3}%$/'],
            'colors.primaryForeground' => ['sometimes', 'string', 'regex:/^\d{1,3}\s+\d{1,3}%\s+\d{1,3}%$/'],
            'colors.secondary' => ['sometimes', 'string', 'regex:/^\d{1,3}\s+\d{1,3}%\s+\d{1,3}%$/'],
            'colors.secondaryForeground' => ['sometimes', 'string', 'regex:/^\d{1,3}\s+\d{1,3}%\s+\d{1,3}%$/'],
            'colors.accent' => ['sometimes', 'string', 'regex:/^\d{1,3}\s+\d{1,3}%\s+\d{1,3}%$/'],
            'colors.accentForeground' => ['sometimes', 'string', 'regex:/^\d{1,3}\s+\d{1,3}%\s+\d{1,3}%$/'],
            'colors.success' => ['sometimes', 'string', 'regex:/^\d{1,3}\s+\d{1,3}%\s+\d{1,3}%$/'],
            'colors.warning' => ['sometimes', 'string', 'regex:/^\d{1,3}\s+\d{1,3}%\s+\d{1,3}%$/'],
            'colors.error' => ['sometimes', 'string', 'regex:/^\d{1,3}\s+\d{1,3}%\s+\d{1,3}%$/'],
            'colors.info' => ['sometimes', 'string', 'regex:/^\d{1,3}\s+\d{1,3}%\s+\d{1,3}%$/'],
            'branding' => ['sometimes', 'array'],
            'branding.hospitalName' => ['sometimes', 'string', 'max:255'],
            'branding.logoUrl' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'colors.primary.regex' => 'Primary color must be in HSL format (e.g., "210 90% 45%").',
            'colors.primaryForeground.regex' => 'Primary foreground color must be in HSL format.',
            'colors.secondary.regex' => 'Secondary color must be in HSL format.',
            'colors.secondaryForeground.regex' => 'Secondary foreground color must be in HSL format.',
            'colors.accent.regex' => 'Accent color must be in HSL format.',
            'colors.accentForeground.regex' => 'Accent foreground color must be in HSL format.',
            'colors.success.regex' => 'Success color must be in HSL format.',
            'colors.warning.regex' => 'Warning color must be in HSL format.',
            'colors.error.regex' => 'Error color must be in HSL format.',
            'colors.info.regex' => 'Info color must be in HSL format.',
            'branding.hospitalName.max' => 'Hospital name cannot exceed 255 characters.',
            'branding.logoUrl.max' => 'Logo URL cannot exceed 500 characters.',
        ];
    }

    /**
     * Additional validation for HSL color ranges.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('colors')) {
                $themeService = app(ThemeSettingService::class);
                $colors = $this->input('colors', []);

                foreach ($colors as $key => $value) {
                    if (is_string($value) && ! $themeService->isValidHslColor($value)) {
                        $validator->errors()->add(
                            "colors.{$key}",
                            "The {$key} color has invalid HSL values. Hue must be 0-360, saturation and lightness must be 0-100%."
                        );
                    }
                }
            }
        });
    }
}
