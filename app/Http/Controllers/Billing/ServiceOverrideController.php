<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\PatientCheckin;
use App\Models\ServiceAccessOverride;
use App\Services\OverrideAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ServiceOverrideController extends Controller
{
    public function __construct(
        private OverrideAuditService $overrideAuditService
    ) {}

    /**
     * Activate service access override
     */
    public function activate(Request $request, PatientCheckin $checkin)
    {
        $this->authorize('overrideService', $checkin);

        $validated = $request->validate([
            'service_type' => 'required|in:consultation,laboratory,pharmacy,ward',
            'service_code' => 'nullable|string|max:50',
            'reason' => 'required|string|min:20|max:500',
            'duration_hours' => 'nullable|integer|min:1|max:24',
        ]);

        // Check if override already exists
        $existingOverride = ServiceAccessOverride::active()
            ->forService($validated['service_type'], $validated['service_code'] ?? null)
            ->where('patient_checkin_id', $checkin->id)
            ->first();

        if ($existingOverride) {
            return back()->withErrors([
                'error' => 'An active override already exists for this service',
            ]);
        }

        try {
            $durationHours = $validated['duration_hours'] ?? 2;

            $override = ServiceAccessOverride::create([
                'patient_checkin_id' => $checkin->id,
                'service_type' => $validated['service_type'],
                'service_code' => $validated['service_code'] ?? null,
                'reason' => $validated['reason'],
                'authorized_by' => auth()->id(),
                'authorized_at' => now(),
                'expires_at' => now()->addHours($durationHours),
                'is_active' => true,
            ]);

            // Log to audit trail
            $this->overrideAuditService->logOverrideActivation($override);

            Log::channel('stack')->info('Service access override activated', [
                'override_id' => $override->id,
                'patient_checkin_id' => $checkin->id,
                'service_type' => $validated['service_type'],
                'service_code' => $validated['service_code'] ?? null,
                'authorized_by' => auth()->id(),
                'authorized_by_name' => auth()->user()->name,
                'reason' => $validated['reason'],
                'expires_at' => $override->expires_at,
                'timestamp' => now(),
            ]);

            return back()->with('success', "Service access override activated for {$validated['service_type']}");
        } catch (\Exception $e) {
            Log::error('Service override activation failed', [
                'patient_checkin_id' => $checkin->id,
                'service_type' => $validated['service_type'],
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to activate service override. Please try again.',
            ]);
        }
    }

    /**
     * Deactivate override early
     */
    public function deactivate(ServiceAccessOverride $override)
    {
        $this->authorize('overrideService', $override->patientCheckin);

        try {
            $override->update(['is_active' => false]);

            // Log to audit trail
            $this->overrideAuditService->logOverrideDeactivation($override, auth()->id());

            Log::channel('stack')->info('Service access override deactivated', [
                'override_id' => $override->id,
                'patient_checkin_id' => $override->patient_checkin_id,
                'service_type' => $override->service_type,
                'deactivated_by' => auth()->id(),
                'deactivated_by_name' => auth()->user()->name,
                'timestamp' => now(),
            ]);

            return back()->with('success', 'Service access override deactivated');
        } catch (\Exception $e) {
            Log::error('Service override deactivation failed', [
                'override_id' => $override->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to deactivate service override. Please try again.',
            ]);
        }
    }

    /**
     * Get active overrides for a patient
     */
    public function index(PatientCheckin $checkin)
    {
        $overrides = ServiceAccessOverride::where('patient_checkin_id', $checkin->id)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->with('authorizedBy:id,name')
            ->get()
            ->map(function ($override) {
                return [
                    'id' => $override->id,
                    'service_type' => $override->service_type,
                    'service_code' => $override->service_code,
                    'reason' => $override->reason,
                    'authorized_by' => [
                        'id' => $override->authorizedBy->id,
                        'name' => $override->authorizedBy->name,
                    ],
                    'authorized_at' => $override->authorized_at->format('M j, Y g:i A'),
                    'expires_at' => $override->expires_at->format('M j, Y g:i A'),
                    'remaining_duration' => $override->getRemainingDuration(),
                    'is_expired' => $override->isExpired(),
                ];
            });

        return response()->json(['overrides' => $overrides]);
    }
}
