<?php

namespace App\Http\Controllers\Ward;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustScheduleTimeRequest;
use App\Http\Requests\ConfigureScheduleRequest;
use App\Http\Requests\DiscontinuePrescriptionRequest;
use App\Models\MedicationAdministration;
use App\Models\Prescription;
use App\Services\MedicationScheduleService;
use Illuminate\Http\JsonResponse;

class MedicationScheduleController extends Controller
{
    public function __construct(
        private readonly MedicationScheduleService $scheduleService
    ) {}

    /**
     * Get smart default time patterns based on frequency and current time.
     */
    public function smartDefaults(Prescription $prescription): JsonResponse
    {
        // Parse duration string to extract number of days
        $durationDays = $this->parseDurationToDays($prescription->duration);

        $defaults = $this->scheduleService->generateSmartDefaults(
            $prescription->frequency,
            now(),
            $durationDays
        );

        return response()->json([
            'prescription' => $prescription->only(['id', 'frequency', 'duration']),
            'defaults' => $defaults,
        ]);
    }

    /**
     * Parse duration string to extract number of days.
     */
    private function parseDurationToDays(string $duration): int
    {
        // Extract number from duration string (e.g., "5 days" -> 5, "2 weeks" -> 14)
        if (preg_match('/(\d+)\s*(day|days)/i', $duration, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/(\d+)\s*(week|weeks)/i', $duration, $matches)) {
            return (int) $matches[1] * 7;
        }

        if (preg_match('/(\d+)\s*(month|months)/i', $duration, $matches)) {
            return (int) $matches[1] * 30;
        }

        // Default to 7 days if unable to parse
        return 7;
    }

    /**
     * Configure schedule pattern and generate medication administrations.
     */
    public function configureSchedule(
        ConfigureScheduleRequest $request,
        Prescription $prescription
    ) {
        // Save the schedule pattern to the prescription
        $prescription->update([
            'schedule_pattern' => $request->input('schedule_pattern'),
        ]);

        // Generate the medication administration records
        $this->scheduleService->generateScheduleFromPattern($prescription);

        return back()->with('success', 'Medication schedule configured successfully.');
    }

    /**
     * Reconfigure existing schedule with new pattern.
     */
    public function reconfigureSchedule(
        ConfigureScheduleRequest $request,
        Prescription $prescription
    ) {
        $this->scheduleService->reconfigureSchedule(
            $prescription,
            $request->input('schedule_pattern'),
            $request->user()
        );

        return back()->with('success', 'Medication schedule reconfigured successfully.');
    }

    /**
     * Get all medication administrations for a prescription with adjustment history.
     */
    public function index(Prescription $prescription): JsonResponse
    {
        $administrations = MedicationAdministration::where('prescription_id', $prescription->id)
            ->with([
                'scheduleAdjustments.adjustedBy:id,name',
                'latestAdjustment.adjustedBy:id,name',
                'administeredBy:id,name',
            ])
            ->orderBy('scheduled_time', 'asc')
            ->get();

        return response()->json([
            'prescription' => $prescription->load(['drug:id,name,strength', 'discontinuedBy:id,name']),
            'administrations' => $administrations,
        ]);
    }

    /**
     * Adjust the scheduled time for a medication administration.
     */
    public function adjustTime(
        AdjustScheduleTimeRequest $request,
        MedicationAdministration $administration
    ) {
        try {
            $this->scheduleService->adjustScheduleTime(
                $administration,
                $request->date('scheduled_time'),
                $request->user(),
                $request->input('reason')
            );

            return back()->with('success', 'Medication schedule adjusted successfully.');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['scheduled_time' => $e->getMessage()]);
        }
    }

    /**
     * Get adjustment history for a specific medication administration.
     */
    public function adjustmentHistory(MedicationAdministration $administration): JsonResponse
    {
        $adjustments = $administration->scheduleAdjustments()
            ->with('adjustedBy:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'administration' => $administration,
            'adjustments' => $adjustments,
        ]);
    }

    /**
     * Discontinue a prescription and cancel future administrations.
     */
    public function discontinue(
        DiscontinuePrescriptionRequest $request,
        Prescription $prescription
    ) {
        $this->scheduleService->discontinuePrescription(
            $prescription,
            $request->user(),
            $request->input('reason')
        );

        return back()->with('success', 'Prescription discontinued successfully.');
    }
}
