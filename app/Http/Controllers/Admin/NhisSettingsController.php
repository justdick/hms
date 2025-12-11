<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NhisSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NhisSettingsController extends Controller
{
    public function index()
    {
        $this->authorize('view', NhisSettings::class);

        $settings = NhisSettings::getInstance();

        return Inertia::render('Admin/NhisSettings/Index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $this->authorize('manage', NhisSettings::class);

        $validated = $request->validate([
            'verification_mode' => 'required|in:manual,extension',
            'nhia_portal_url' => 'required|url',
            'facility_code' => 'nullable|string|max:50',
            'nhia_username' => 'nullable|string|max:100',
            'nhia_password' => 'nullable|string|max:100',
            'auto_open_portal' => 'required|boolean',
        ]);

        $settings = NhisSettings::getInstance();

        // Only update password if provided
        if (empty($validated['nhia_password'])) {
            unset($validated['nhia_password']);
        }

        $settings->update($validated);

        return redirect()->back()->with('success', 'NHIS settings updated successfully');
    }
}
