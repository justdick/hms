<?php

namespace App\Http\Controllers\Backup;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBackupSettingsRequest;
use App\Models\Backup;
use App\Models\BackupSettings;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class BackupSettingsController extends Controller
{
    public function __construct(
        protected GoogleDriveService $googleDriveService
    ) {}

    /**
     * Display the backup settings form.
     */
    public function edit(): Response
    {
        $this->authorize('manageSettings', Backup::class);

        $settings = BackupSettings::getInstance();

        // Don't expose credentials to frontend, just indicate if configured
        $settingsData = $settings->toArray();
        $settingsData['has_google_credentials'] = ! empty($settings->google_credentials);
        unset($settingsData['google_credentials']);

        return Inertia::render('Backup/Settings', [
            'settings' => $settingsData,
        ]);
    }

    /**
     * Update the backup settings.
     */
    public function update(UpdateBackupSettingsRequest $request): RedirectResponse
    {
        $this->authorize('manageSettings', Backup::class);

        $validated = $request->validated();

        $settings = BackupSettings::getInstance();

        // Handle Google credentials separately - only update if provided
        if (isset($validated['google_credentials']) && ! empty($validated['google_credentials'])) {
            $settings->google_credentials = $validated['google_credentials'];
        }
        unset($validated['google_credentials']);

        $settings->update($validated);

        return redirect()->route('admin.backups.settings')
            ->with('success', 'Backup settings updated successfully.');
    }

    /**
     * Test Google Drive connection.
     */
    public function testGoogleDrive(): JsonResponse
    {
        $this->authorize('manageSettings', Backup::class);

        try {
            if (! $this->googleDriveService->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google Drive is not configured. Please provide credentials and folder ID.',
                ]);
            }

            $this->googleDriveService->testConnection();

            return response()->json([
                'success' => true,
                'message' => 'Google Drive connection successful!',
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
