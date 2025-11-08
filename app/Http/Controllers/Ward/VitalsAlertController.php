<?php

namespace App\Http\Controllers\Ward;

use App\Http\Controllers\Controller;
use App\Models\VitalsAlert;
use App\Services\VitalsAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VitalsAlertController extends Controller
{
    public function __construct(
        public VitalsAlertService $alertService
    ) {}

    public function active(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get active alerts for user's wards
        $alerts = $this->alertService->getActiveAlertsForUser($user);

        // Transform alerts for frontend
        $transformedAlerts = $alerts->map(function ($alert) {
            $admission = $alert->patientAdmission;
            $patient = $admission->patient;

            return [
                'id' => $alert->id,
                'patient_admission_id' => $alert->patient_admission_id,
                'ward_id' => $admission->ward_id,
                'patient_name' => $patient->first_name.' '.$patient->last_name,
                'bed_number' => $admission->bed?->bed_number ?? 'No Bed',
                'ward_name' => $admission->ward?->name ?? 'Unknown Ward',
                'due_at' => $alert->due_at->toIso8601String(),
                'status' => $alert->status,
                'time_overdue_minutes' => $alert->status === 'overdue'
                    ? now()->diffInMinutes($alert->due_at->addMinutes(15))
                    : null,
            ];
        });

        return response()->json([
            'alerts' => $transformedAlerts,
        ]);
    }

    public function acknowledge(Request $request, VitalsAlert $alert): JsonResponse
    {
        $user = $request->user();

        // Verify user has access to this alert's ward
        $admission = $alert->patientAdmission;
        if (! $admission || ! $admission->ward_id) {
            abort(403, 'Unauthorized access to this alert.');
        }

        $this->alertService->acknowledgeAlert($alert, $user);

        return response()->json([
            'message' => 'Alert acknowledged successfully.',
            'alert' => [
                'id' => $alert->id,
                'acknowledged_at' => $alert->acknowledged_at->toIso8601String(),
            ],
        ]);
    }

    public function dismiss(Request $request, VitalsAlert $alert): JsonResponse
    {
        $user = $request->user();

        // Verify user has access to this alert's ward
        $admission = $alert->patientAdmission;
        if (! $admission || ! $admission->ward_id) {
            abort(403, 'Unauthorized access to this alert.');
        }

        $this->alertService->dismissAlert($alert, $user);

        return response()->json([
            'message' => 'Alert dismissed successfully.',
        ]);
    }
}
