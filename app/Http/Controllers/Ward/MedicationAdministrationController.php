<?php

namespace App\Http\Controllers\Ward;

use App\Http\Controllers\Controller;
use App\Models\MedicationAdministration;
use App\Models\PatientAdmission;
use App\Models\Prescription;
use Illuminate\Http\Request;

/**
 * Medication Administration Controller
 *
 * Handles on-demand recording of medication administrations for admitted patients.
 * Instead of pre-scheduling doses, nurses record administrations as they happen.
 */
class MedicationAdministrationController extends Controller
{
    /**
     * Get active prescriptions and recent administrations for an admitted patient.
     */
    public function index(PatientAdmission $admission)
    {
        $this->authorize('viewAny', MedicationAdministration::class);

        // Get active prescriptions for this admission (from ward rounds and consultations)
        $prescriptions = $this->getActivePrescriptions($admission);

        // Get recent administrations (last 7 days + future dates)
        $recentAdministrations = MedicationAdministration::where('patient_admission_id', $admission->id)
            ->where('administered_at', '>=', now()->subDays(7)->startOfDay())
            ->with(['prescription.drug:id,name,strength', 'administeredBy:id,name'])
            ->orderBy('administered_at', 'desc')
            ->get();

        // Calculate today's count for each prescription
        $todayAdministrations = $recentAdministrations->filter(function ($admin) {
            return $admin->administered_at->isToday();
        });

        // Build response with prescription status
        $prescriptionsWithStatus = $prescriptions->map(function ($prescription) use ($todayAdministrations) {
            $todayCount = $todayAdministrations
                ->where('prescription_id', $prescription->id)
                ->where('status', 'given')
                ->count();

            $expectedDoses = $prescription->getExpectedDosesPerDay();

            return [
                'prescription' => $prescription,
                'today_given' => $todayCount,
                'expected_doses' => $expectedDoses,
                'is_prn' => $prescription->isPrn(),
                'last_administration' => $todayAdministrations
                    ->where('prescription_id', $prescription->id)
                    ->first(),
            ];
        });

        return response()->json([
            'prescriptions' => $prescriptionsWithStatus,
            'today_administrations' => $recentAdministrations,
        ]);
    }

    /**
     * Record a new medication administration.
     */
    public function store(Request $request, PatientAdmission $admission)
    {
        $this->authorize('create', MedicationAdministration::class);

        $rules = [
            'prescription_id' => 'required|exists:prescriptions,id',
            'dosage_given' => 'required|string|max:100',
            'route' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
        ];

        // Only allow administered_at if user has permission
        if (auth()->user()->can('medications.edit-timestamp')) {
            $rules['administered_at'] = 'nullable|date';
        }

        $validated = $request->validate($rules);

        // Verify prescription belongs to this admission
        $prescription = Prescription::findOrFail($validated['prescription_id']);
        $prescriptionAdmission = $prescription->getPatientAdmission();

        if (! $prescriptionAdmission || $prescriptionAdmission->id !== $admission->id) {
            return back()->withErrors(['prescription_id' => 'This prescription does not belong to this admission.']);
        }

        // Check prescription is not discontinued
        if ($prescription->isDiscontinued()) {
            return back()->withErrors(['prescription_id' => 'Cannot administer discontinued medication.']);
        }

        // Determine administered_at: use provided value if permitted, otherwise now()
        $administeredAt = now();
        if (auth()->user()->can('medications.edit-timestamp') && ! empty($validated['administered_at'])) {
            $administeredAt = $validated['administered_at'];
        }

        MedicationAdministration::create([
            'prescription_id' => $validated['prescription_id'],
            'patient_admission_id' => $admission->id,
            'administered_by_id' => auth()->id(),
            'administered_at' => $administeredAt,
            'status' => 'given',
            'dosage_given' => $validated['dosage_given'],
            'route' => $validated['route'] ?? 'oral',
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Medication administered successfully.');
    }

    /**
     * Record medication as held (e.g., patient NPO, clinical contraindication).
     */
    public function hold(Request $request, PatientAdmission $admission)
    {
        $this->authorize('create', MedicationAdministration::class);

        $rules = [
            'prescription_id' => 'required|exists:prescriptions,id',
            'notes' => 'required|string|max:500|min:10',
        ];

        // Only allow administered_at if user has permission
        if (auth()->user()->can('medications.edit-timestamp')) {
            $rules['administered_at'] = 'nullable|date';
        }

        $validated = $request->validate($rules);

        // Verify prescription belongs to this admission
        $prescription = Prescription::findOrFail($validated['prescription_id']);
        $prescriptionAdmission = $prescription->getPatientAdmission();

        if (! $prescriptionAdmission || $prescriptionAdmission->id !== $admission->id) {
            return back()->withErrors(['prescription_id' => 'This prescription does not belong to this admission.']);
        }

        // Determine administered_at: use provided value if permitted, otherwise now()
        $administeredAt = now();
        if (auth()->user()->can('medications.edit-timestamp') && ! empty($validated['administered_at'])) {
            $administeredAt = $validated['administered_at'];
        }

        MedicationAdministration::create([
            'prescription_id' => $validated['prescription_id'],
            'patient_admission_id' => $admission->id,
            'administered_by_id' => auth()->id(),
            'administered_at' => $administeredAt,
            'status' => 'held',
            'notes' => $validated['notes'],
        ]);

        return back()->with('success', 'Medication held.');
    }

    /**
     * Record medication as refused by patient.
     */
    public function refuse(Request $request, PatientAdmission $admission)
    {
        $this->authorize('create', MedicationAdministration::class);

        $rules = [
            'prescription_id' => 'required|exists:prescriptions,id',
            'notes' => 'nullable|string|max:500',
        ];

        // Only allow administered_at if user has permission
        if (auth()->user()->can('medications.edit-timestamp')) {
            $rules['administered_at'] = 'nullable|date';
        }

        $validated = $request->validate($rules);

        // Verify prescription belongs to this admission
        $prescription = Prescription::findOrFail($validated['prescription_id']);
        $prescriptionAdmission = $prescription->getPatientAdmission();

        if (! $prescriptionAdmission || $prescriptionAdmission->id !== $admission->id) {
            return back()->withErrors(['prescription_id' => 'This prescription does not belong to this admission.']);
        }

        // Determine administered_at: use provided value if permitted, otherwise now()
        $administeredAt = now();
        if (auth()->user()->can('medications.edit-timestamp') && ! empty($validated['administered_at'])) {
            $administeredAt = $validated['administered_at'];
        }

        MedicationAdministration::create([
            'prescription_id' => $validated['prescription_id'],
            'patient_admission_id' => $admission->id,
            'administered_by_id' => auth()->id(),
            'administered_at' => $administeredAt,
            'status' => 'refused',
            'notes' => $validated['notes'] ?? 'Patient refused medication',
        ]);

        return back()->with('success', 'Medication marked as refused.');
    }

    /**
     * Record medication as omitted (missed for other reasons).
     */
    public function omit(Request $request, PatientAdmission $admission)
    {
        $this->authorize('create', MedicationAdministration::class);

        $rules = [
            'prescription_id' => 'required|exists:prescriptions,id',
            'notes' => 'required|string|max:500|min:10',
        ];

        // Only allow administered_at if user has permission
        if (auth()->user()->can('medications.edit-timestamp')) {
            $rules['administered_at'] = 'nullable|date';
        }

        $validated = $request->validate($rules);

        // Verify prescription belongs to this admission
        $prescription = Prescription::findOrFail($validated['prescription_id']);
        $prescriptionAdmission = $prescription->getPatientAdmission();

        if (! $prescriptionAdmission || $prescriptionAdmission->id !== $admission->id) {
            return back()->withErrors(['prescription_id' => 'This prescription does not belong to this admission.']);
        }

        // Determine administered_at: use provided value if permitted, otherwise now()
        $administeredAt = now();
        if (auth()->user()->can('medications.edit-timestamp') && ! empty($validated['administered_at'])) {
            $administeredAt = $validated['administered_at'];
        }

        MedicationAdministration::create([
            'prescription_id' => $validated['prescription_id'],
            'patient_admission_id' => $admission->id,
            'administered_by_id' => auth()->id(),
            'administered_at' => $administeredAt,
            'status' => 'omitted',
            'notes' => $validated['notes'],
        ]);

        return back()->with('success', 'Medication marked as omitted.');
    }

    /**
     * Delete a medication administration record.
     * Can only delete within 2 hours of recording.
     */
    public function destroy(PatientAdmission $admission, MedicationAdministration $medication)
    {
        $this->authorize('delete', $medication);

        // Verify the medication administration belongs to this admission
        if ($medication->patient_admission_id !== $admission->id) {
            return back()->withErrors(['medication' => 'This record does not belong to this admission.']);
        }

        // Record who deleted it before soft deleting
        $medication->update(['deleted_by_id' => auth()->id()]);
        $medication->delete();

        return back()->with('success', 'Medication administration record deleted.');
    }

    /**
     * Discontinue a prescription.
     * This stops the medication from appearing in the MAR slide-over.
     */
    public function discontinue(Request $request, Prescription $prescription)
    {
        $this->authorize('prescriptions.discontinue');

        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ]);

        // Check if already discontinued
        if ($prescription->isDiscontinued()) {
            return back()->withErrors(['reason' => 'This prescription has already been discontinued.']);
        }

        // Discontinue the prescription
        $prescription->discontinue(auth()->user(), $validated['reason']);

        return back()->with('success', 'Medication discontinued successfully.');
    }

    /**
     * Resume a discontinued prescription.
     * This allows the medication to appear in the MAR slide-over again.
     */
    public function resume(Request $request, Prescription $prescription)
    {
        $this->authorize('prescriptions.resume');

        // Check if not discontinued
        if (! $prescription->isDiscontinued()) {
            return back()->withErrors(['error' => 'This prescription is not discontinued.']);
        }

        // Resume the prescription (clears discontinuation fields and records audit)
        $prescription->resume(auth()->user());

        return back()->with('success', 'Medication resumed successfully.');
    }

    /**
     * Mark a prescription as completed.
     * This indicates the patient has finished the full course of medication.
     */
    public function complete(Request $request, Prescription $prescription)
    {
        $this->authorize('prescriptions.discontinue'); // Same permission as discontinue

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        // Check if already completed or discontinued
        if ($prescription->isCompleted()) {
            return back()->withErrors(['reason' => 'This prescription has already been marked as completed.']);
        }

        if ($prescription->isDiscontinued()) {
            return back()->withErrors(['reason' => 'Cannot complete a discontinued prescription.']);
        }

        // Mark the prescription as completed
        $prescription->complete(auth()->user(), $validated['reason'] ?? null);

        return back()->with('success', 'Medication marked as completed.');
    }

    /**
     * Get active prescriptions for an admission.
     */
    private function getActivePrescriptions(PatientAdmission $admission)
    {
        // Get prescriptions from ward rounds
        $wardRoundPrescriptions = Prescription::whereHasMorph(
            'prescribable',
            ['App\Models\WardRound'],
            function ($query) use ($admission) {
                $query->where('patient_admission_id', $admission->id);
            }
        )
            ->active()
            ->with(['drug:id,name,strength,drug_code'])
            ->get();

        // Get prescriptions from consultation (if admission came from consultation)
        $consultationPrescriptions = collect();
        if ($admission->consultation_id) {
            $consultationPrescriptions = Prescription::where('consultation_id', $admission->consultation_id)
                ->active()
                ->with(['drug:id,name,strength,drug_code'])
                ->get();
        }

        return $wardRoundPrescriptions->merge($consultationPrescriptions)->unique('id');
    }
}
