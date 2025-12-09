<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreThemeSettingRequest;
use App\Services\ThemeSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ThemeSettingController extends Controller
{
    public function __construct(protected ThemeSettingService $themeService) {}

    /**
     * Display the theme settings page.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', \App\Models\ThemeSetting::class);

        return Inertia::render('Admin/ThemeSettings/Index', [
            'theme' => $this->themeService->getThemeWithFallback(),
            'canManageTheme' => $request->user()?->can('settings.manage-theme') ?? false,
        ]);
    }

    /**
     * Get the current theme configuration.
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => $this->themeService->getThemeWithFallback(),
        ]);
    }

    /**
     * Update the theme configuration.
     */
    public function update(StoreThemeSettingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (isset($validated['colors'])) {
            $this->themeService->updateColors($validated['colors']);
        }

        if (isset($validated['branding'])) {
            $this->themeService->updateBranding($validated['branding']);
        }

        return response()->json([
            'data' => $this->themeService->getTheme(),
            'message' => 'Theme settings updated successfully.',
        ]);
    }

    /**
     * Reset theme to default values.
     */
    public function reset(Request $request): JsonResponse
    {
        if (! $request->user()?->can('settings.manage-theme')) {
            abort(403, 'Unauthorized to reset theme settings.');
        }

        $theme = $this->themeService->resetTheme();

        return response()->json([
            'data' => $theme,
            'message' => 'Theme settings reset to defaults.',
        ]);
    }

    /**
     * Upload a logo image.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        if (! $request->user()?->can('settings.manage-theme')) {
            abort(403, 'Unauthorized to upload logo.');
        }

        $request->validate([
            'logo' => [
                'required',
                'file',
                'mimes:png,jpg,jpeg,svg',
                'max:2048', // 2MB max
            ],
        ], [
            'logo.required' => 'Please select a logo file to upload.',
            'logo.file' => 'The logo must be a valid file.',
            'logo.mimes' => 'The logo must be a PNG, JPG, JPEG, or SVG file.',
            'logo.max' => 'The logo file size cannot exceed 2MB.',
        ]);

        $file = $request->file('logo');

        // Delete old logo if exists
        $currentBranding = $this->themeService->getTheme()['branding'];
        if (! empty($currentBranding['logoUrl'])) {
            $oldPath = str_replace('/storage/', '', $currentBranding['logoUrl']);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        // Store new logo
        $path = $file->store('logos', 'public');
        $logoUrl = '/storage/'.$path;

        // Update branding with new logo URL
        $this->themeService->updateBranding(['logoUrl' => $logoUrl]);

        return response()->json([
            'data' => [
                'logoUrl' => $logoUrl,
            ],
            'message' => 'Logo uploaded successfully.',
        ]);
    }
}
