<?php

namespace App\Http\Controllers\Ward;

use App\Http\Controllers\Controller;
use App\Models\MedicationAdministration;
use App\Models\PatientAdmission;
use Illuminate\Http\Request;

class MedicationAdministrationController extends Controller
{
    /**
     * Get all medication administrations for an admitted patient.
     */
    public function index(PatientAdmission $admission)
    {
        $this->authorize('viewAny', MedicationAdministration::class);

        $medications = MedicationAdministration::where('patient_admission_id', $admission->id)
            ->with([
                'prescription.drug:id,name,strength',
                'administeredBy:id,name',
            ])
            ->orderBy('scheduled_time', 'desc')
            ->get()
            ->groupBy(function ($med) {
                return $med->scheduled_time->format('Y-m-d');
            });

        return response()->json($medications);
    }

    /**
     * Administer a medication to a patient.
     */
    public function administer(Request $request, MedicationAdministration $administration)
    {
        $this->authorize('administer', $administration);

        $validated = $request->validate([
            'dosage_given' => 'required|string|max:100',
            'route' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
        ]);

        $administration->update([
            'status' => 'given',
            'administered_at' => now(),
            'administered_by_id' => auth()->id(),
            'dosage_given' => $validated['dosage_given'],
            'route' => $validated['route'] ?? $administration->route,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Medication administered successfully.');
    }

    /**
     * Hold a scheduled medication (e.g., patient NPO, clinical contraindication).
     */
    public function hold(Request $request, MedicationAdministration $administration)
    {
        $this->authorize('hold', $administration);

        $validated = $request->validate([
            'notes' => 'required|string|max:500|min:10',
        ]);

        $administration->update([
            'status' => 'held',
            'administered_by_id' => auth()->id(),
            'notes' => $validated['notes'],
        ]);

        return back()->with('success', 'Medication held.');
    }

    /**
     * Mark a medication as refused by patient.
     */
    public function refuse(Request $request, MedicationAdministration $administration)
    {
        $this->authorize('refuse', $administration);

        $validated = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        $administration->update([
            'status' => 'refused',
            'administered_by_id' => auth()->id(),
            'notes' => $validated['notes'] ?? 'Patient refused medication',
        ]);

        return back()->with('success', 'Medication marked as refused.');
    }

    /**
     * Mark a medication as omitted (missed for other reasons).
     */
    public function omit(Request $request, MedicationAdministration $administration)
    {
        $this->authorize('hold', $administration); // Same permission as hold

        $validated = $request->validate([
            'notes' => 'required|string|max:500|min:10',
        ]);

        $administration->update([
            'status' => 'omitted',
            'administered_by_id' => auth()->id(),
            'notes' => $validated['notes'],
        ]);

        return back()->with('success', 'Medication marked as omitted.');
    }
}
