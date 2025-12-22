<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\PatientInsurance;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PatientInsuranceController extends Controller
{
    /**
     * Sync insurance coverage dates from NHIS verification.
     * Called automatically during check-in when dates differ.
     */
    public function syncDates(Request $request, PatientInsurance $patientInsurance)
    {
        $validated = $request->validate([
            'coverage_start_date' => 'required|date',
            'coverage_end_date' => 'required|date|after_or_equal:coverage_start_date',
        ]);

        // Parse dates
        $newStartDate = Carbon::parse($validated['coverage_start_date'])->startOfDay();
        $newEndDate = Carbon::parse($validated['coverage_end_date'])->startOfDay();

        // Check if dates actually changed
        $startChanged = ! $patientInsurance->coverage_start_date?->eq($newStartDate);
        $endChanged = ! $patientInsurance->coverage_end_date?->eq($newEndDate);

        if (! $startChanged && ! $endChanged) {
            return back()->with('info', 'Insurance dates are already up to date.');
        }

        // Store old dates for the message
        $oldStartDate = $patientInsurance->coverage_start_date?->format('Y-m-d');
        $oldEndDate = $patientInsurance->coverage_end_date?->format('Y-m-d');

        // Update the dates
        $patientInsurance->update([
            'coverage_start_date' => $newStartDate,
            'coverage_end_date' => $newEndDate,
        ]);

        // Build message about what changed
        $changes = [];
        if ($startChanged) {
            $changes[] = "start date: {$oldStartDate} → {$newStartDate->format('Y-m-d')}";
        }
        if ($endChanged) {
            $changes[] = "end date: {$oldEndDate} → {$newEndDate->format('Y-m-d')}";
        }

        return back()->with('success', 'Insurance dates updated: '.implode(', ', $changes));
    }
}
