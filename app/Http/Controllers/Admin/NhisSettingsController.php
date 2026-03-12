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
            'settings' => [
                'id' => $settings->id,
                'verification_mode' => $settings->verification_mode,
                'nhia_portal_url' => $settings->nhia_portal_url,
                'facility_code' => $settings->facility_code,
                'nhia_username' => $settings->nhia_username,
                'auto_open_portal' => $settings->auto_open_portal,
                'has_password' => $settings->getRawOriginal('nhia_password') !== null,
            ],
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

        // Use a query update to avoid triggering the encrypted cast on the
        // existing (potentially undecryptable) nhia_password value.
        $settings = NhisSettings::getInstance();

        $updateData = collect($validated)->except('nhia_password')->toArray();

        // Only update password if a new one was provided — encrypt it manually
        // Use encryptString() to match the 'encrypted' cast (no serialization)
        if (! empty($validated['nhia_password'])) {
            $updateData['nhia_password'] = \Illuminate\Support\Facades\Crypt::encryptString($validated['nhia_password']);
        }

        NhisSettings::where('id', $settings->id)->update($updateData);

        return redirect()->back()->with('success', 'NHIS settings updated successfully');
    }
}
