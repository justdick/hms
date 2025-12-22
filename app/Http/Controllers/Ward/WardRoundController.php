<?php

namespace App\Http\Controllers\Ward;

use App\Events\LabTestOrdered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Prescription\StorePrescriptionRequest;
use App\Http\Requests\StoreWardRoundRequest;
use App\Models\MinorProcedureType;
use App\Models\PatientAdmission;
use App\Models\WardRound;
use Inertia\Inertia;

class WardRoundController extends Controller
{
    public function index(PatientAdmission $admission)
    {
        $this->authorize('viewAny', WardRound::class);

        $rounds = $admission->wardRounds()
            ->with('doctor:id,name')
            ->orderBy('round_datetime', 'desc')
            ->get();

        return response()->json([
            'ward_rounds' => $rounds,
        ]);
    }

    public function create(PatientAdmission $admission)
    {
        $this->authorize('create', WardRound::class);

        $admission->load([
            'patient',
            'ward',
            'admissionConsultation',
            'activeDiagnoses.diagnosedBy',
            'vitalSigns' => fn ($q) => $q->latest('recorded_at')->limit(5),
        ]);

        $patient = $admission->patient;

        // Check if there's already an in-progress ward round
        $existingInProgress = WardRound::where('patient_admission_id', $admission->id)
            ->where('status', 'in_progress')
            ->first();

        // If there's an existing in-progress ward round
        if ($existingInProgress) {
            // Check if user explicitly wants to start a new round (force=true in query params)
            $forceNew = request()->query('force') === 'true';

            if ($forceNew) {
                // Auto-complete the existing in-progress ward round
                $existingInProgress->update(['status' => 'completed']);
            } else {
                // Redirect to edit the existing ward round instead of creating a new one
                return redirect()->route('admissions.ward-rounds.edit', [
                    'admission' => $admission->id,
                    'wardRound' => $existingInProgress->id,
                ]);
            }
        }

        // Calculate day number based on admission date
        $dayNumber = $this->calculateDayNumber($admission);

        // Create a new in-progress ward round
        $wardRound = $admission->wardRounds()->create([
            'doctor_id' => auth()->id(),
            'day_number' => $dayNumber,
            'round_type' => 'daily_round',
            'round_datetime' => now(),
            'status' => 'in_progress',
        ]);

        // Get patient's previous ward rounds for this admission
        $patientHistory = [
            'previousWardRounds' => WardRound::with([
                'doctor:id,name',
            ])
                ->where('patient_admission_id', $admission->id)
                ->where('id', '!=', $wardRound->id)
                ->orderBy('round_datetime', 'desc')
                ->limit(10)
                ->get(),
            'previousPrescriptions' => \App\Models\Prescription::with('prescribable')
                ->where('prescribable_type', WardRound::class)
                ->whereHasMorph('prescribable', [WardRound::class], function ($query) use ($admission) {
                    $query->where('patient_admission_id', $admission->id);
                })
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get(),
            'allergies' => [], // Could be extended with actual allergy data
        ];

        // Patient-level medical histories
        $patientHistories = [
            'past_medical_surgical_history' => $patient->past_medical_surgical_history ?? '',
            'drug_history' => $patient->drug_history ?? '',
            'family_history' => $patient->family_history ?? '',
            'social_history' => $patient->social_history ?? '',
        ];

        // Load existing ward round data with relationships
        $wardRound->load([
            'doctor:id,name',
            'diagnoses.diagnosedBy:id,name',
            'prescriptions.drug',
            'labOrders.labService',
        ]);

        return Inertia::render('Ward/WardRoundCreate', [
            'admission' => $admission,
            'wardRound' => $wardRound,
            'dayNumber' => $dayNumber,
            // Lab services loaded via async search - too many to load upfront
            'patientHistory' => $patientHistory,
            'patientHistories' => $patientHistories,
            'availableDrugs' => \App\Models\Drug::active()->orderBy('name')->get(['id', 'name', 'generic_name', 'brand_name', 'drug_code', 'form', 'strength', 'unit_price', 'unit_type', 'bottle_size']),
            // Diagnoses loaded via async search - too many to load upfront
            'availableProcedures' => MinorProcedureType::active()->orderBy('type')->orderBy('name')->get(['id', 'name', 'code', 'type', 'category', 'price']),
        ]);
    }

    public function show(PatientAdmission $admission, WardRound $wardRound)
    {
        $this->authorize('view', $wardRound);

        // Redirect to patient page where ward round can be viewed in modal
        return redirect()->route('wards.patients.show', [
            'ward' => $admission->ward_id,
            'admission' => $admission->id,
        ]);
    }

    public function edit(PatientAdmission $admission, WardRound $wardRound)
    {
        $this->authorize('update', $wardRound);

        // Only allow editing in-progress ward rounds
        if ($wardRound->status !== 'in_progress') {
            return redirect()->route('admissions.ward-rounds.show', [
                'admission' => $admission->id,
                'wardRound' => $wardRound->id,
            ])->with('error', 'Cannot edit a completed ward round.');
        }

        $admission->load([
            'patient',
            'ward',
            'admissionConsultation',
            'activeDiagnoses.diagnosedBy',
            'vitalSigns' => fn ($q) => $q->latest('recorded_at')->limit(5),
        ]);

        $patient = $admission->patient;

        // Get patient's previous ward rounds for this admission (excluding current)
        $patientHistory = [
            'previousWardRounds' => WardRound::with([
                'doctor:id,name',
            ])
                ->where('patient_admission_id', $admission->id)
                ->where('id', '!=', $wardRound->id)
                ->orderBy('round_datetime', 'desc')
                ->limit(10)
                ->get(),
            'previousPrescriptions' => \App\Models\Prescription::with('prescribable')
                ->where('prescribable_type', WardRound::class)
                ->whereHasMorph('prescribable', [WardRound::class], function ($query) use ($admission, $wardRound) {
                    $query->where('patient_admission_id', $admission->id)
                        ->where('id', '!=', $wardRound->id);
                })
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get(),
            'allergies' => [],
        ];

        // Patient-level medical histories
        $patientHistories = [
            'past_medical_surgical_history' => $patient->past_medical_surgical_history ?? '',
            'drug_history' => $patient->drug_history ?? '',
            'family_history' => $patient->family_history ?? '',
            'social_history' => $patient->social_history ?? '',
        ];

        // Load existing ward round data with relationships
        $wardRound->load([
            'doctor:id,name',
            'diagnoses.diagnosedBy:id,name',
            'prescriptions.drug',
            'labOrders.labService',
            'procedures.procedureType',
            'procedures.doctor:id,name',
        ]);

        return Inertia::render('Ward/WardRoundCreate', [
            'admission' => $admission,
            'wardRound' => $wardRound,
            'dayNumber' => $wardRound->day_number,
            // Lab services loaded via async search - too many to load upfront
            'patientHistory' => $patientHistory,
            'patientHistories' => $patientHistories,
            'availableDrugs' => \App\Models\Drug::active()->orderBy('name')->get(['id', 'name', 'generic_name', 'brand_name', 'drug_code', 'form', 'strength', 'unit_price', 'unit_type', 'bottle_size']),
            // Diagnoses loaded via async search - too many to load upfront
            'availableProcedures' => MinorProcedureType::active()->orderBy('type')->orderBy('name')->get(['id', 'name', 'code', 'type', 'category', 'price']),
        ]);
    }

    public function autoSave(StoreWardRoundRequest $request, PatientAdmission $admission, WardRound $wardRound)
    {
        $this->authorize('update', $wardRound);

        // Only allow auto-save for in-progress ward rounds
        if ($wardRound->status !== 'in_progress') {
            return response()->json(['error' => 'Cannot update completed ward round'], 403);
        }

        $validated = $request->validated();

        // Update consultation notes and round date
        $wardRound->update([
            'round_datetime' => $validated['round_datetime'] ?? $wardRound->round_datetime,
            'presenting_complaint' => $validated['presenting_complaint'] ?? $wardRound->presenting_complaint,
            'history_presenting_complaint' => $validated['history_presenting_complaint'] ?? $wardRound->history_presenting_complaint,
            'on_direct_questioning' => $validated['on_direct_questioning'] ?? $wardRound->on_direct_questioning,
            'examination_findings' => $validated['examination_findings'] ?? $wardRound->examination_findings,
            'assessment_notes' => $validated['assessment_notes'] ?? $wardRound->assessment_notes,
            'plan_notes' => $validated['plan_notes'] ?? $wardRound->plan_notes,
        ]);

        // Update patient medical histories if provided
        $patient = $admission->patient;
        if (isset($validated['past_medical_surgical_history'])) {
            $patient->past_medical_surgical_history = $validated['past_medical_surgical_history'];
        }
        if (isset($validated['drug_history'])) {
            $patient->drug_history = $validated['drug_history'];
        }
        if (isset($validated['family_history'])) {
            $patient->family_history = $validated['family_history'];
        }
        if (isset($validated['social_history'])) {
            $patient->social_history = $validated['social_history'];
        }
        if ($patient->isDirty()) {
            $patient->save();
        }

        return back();
    }

    public function addDiagnosis(PatientAdmission $admission, WardRound $wardRound)
    {
        $this->authorize('update', $wardRound);

        if ($wardRound->status !== 'in_progress') {
            return response()->json(['error' => 'Cannot update completed ward round'], 403);
        }

        $validated = request()->validate([
            'diagnosis_id' => 'required|exists:diagnoses,id',
            'diagnosis_type' => 'required|in:working,complication,comorbidity',
            'clinical_notes' => 'nullable|string',
        ]);

        $diagnosis = \App\Models\Diagnosis::findOrFail($validated['diagnosis_id']);

        $admission->diagnoses()->create([
            'icd_code' => $diagnosis->icd_10 ?? $diagnosis->code,
            'icd_version' => '10',
            'diagnosis_name' => $diagnosis->diagnosis,
            'diagnosis_type' => $validated['diagnosis_type'],
            'source_type' => WardRound::class,
            'source_id' => $wardRound->id,
            'diagnosed_by' => auth()->id(),
            'diagnosed_at' => now(),
            'clinical_notes' => $validated['clinical_notes'] ?? null,
        ]);

        return back();
    }

    public function removeDiagnosis(PatientAdmission $admission, WardRound $wardRound, $diagnosisId)
    {
        $this->authorize('update', $wardRound);

        if ($wardRound->status !== 'in_progress') {
            return response()->json(['error' => 'Cannot update completed ward round'], 403);
        }

        $diagnosis = $admission->diagnoses()
            ->where('id', $diagnosisId)
            ->where('source_type', WardRound::class)
            ->where('source_id', $wardRound->id)
            ->firstOrFail();

        $diagnosis->delete();

        return back();
    }

    public function addPrescription(StorePrescriptionRequest $request, PatientAdmission $admission, WardRound $wardRound)
    {
        $this->authorize('update', $wardRound);

        if ($wardRound->status !== 'in_progress') {
            return response()->json(['error' => 'Cannot update completed ward round'], 403);
        }

        // Get prescription data (handles both Smart and Classic modes)
        $prescriptionData = $request->getPrescriptionData();

        $wardRound->prescriptions()->create([
            'drug_id' => $prescriptionData['drug_id'],
            'medication_name' => $prescriptionData['medication_name'],
            'dose_quantity' => $prescriptionData['dose_quantity'],
            'frequency' => $prescriptionData['frequency'],
            'duration' => $prescriptionData['duration'],
            'quantity' => $prescriptionData['quantity_to_dispense'], // Set for billing
            'quantity_to_dispense' => $prescriptionData['quantity_to_dispense'], // Set for dispensing
            'instructions' => $prescriptionData['instructions'],
            'status' => 'prescribed',
        ]);

        return back();
    }

    public function removePrescription(PatientAdmission $admission, WardRound $wardRound, $prescriptionId)
    {
        $this->authorize('update', $wardRound);

        if ($wardRound->status !== 'in_progress') {
            return response()->json(['error' => 'Cannot update completed ward round'], 403);
        }

        $prescription = $wardRound->prescriptions()
            ->where('id', $prescriptionId)
            ->firstOrFail();

        $prescription->delete();

        return back();
    }

    public function addLabOrder(PatientAdmission $admission, WardRound $wardRound)
    {
        $this->authorize('update', $wardRound);

        if ($wardRound->status !== 'in_progress') {
            return response()->json(['error' => 'Cannot update completed ward round'], 403);
        }

        $validated = request()->validate([
            'lab_service_id' => 'required|exists:lab_services,id',
            'priority' => 'required|in:routine,urgent,stat',
            'special_instructions' => 'nullable|string',
        ]);

        $labOrder = $wardRound->labOrders()->create([
            'lab_service_id' => $validated['lab_service_id'],
            'priority' => $validated['priority'],
            'special_instructions' => $validated['special_instructions'] ?? null,
            'ordered_by' => auth()->id(),
            'ordered_at' => now(),
            'status' => 'ordered',
        ]);

        $labOrder->load('labService');

        // Fire lab test ordered event for billing
        event(new LabTestOrdered($labOrder));

        return back();
    }

    public function removeLabOrder(PatientAdmission $admission, WardRound $wardRound, $labOrderId)
    {
        $this->authorize('update', $wardRound);

        if ($wardRound->status !== 'in_progress') {
            return response()->json(['error' => 'Cannot update completed ward round'], 403);
        }

        $labOrder = $wardRound->labOrders()
            ->where('id', $labOrderId)
            ->firstOrFail();

        // Prevent deletion if sample has been collected or test is in progress
        if (in_array($labOrder->status, ['sample_collected', 'in_progress', 'completed'])) {
            return back()->with('error', 'Cannot delete lab order - sample has been collected or test is in progress.');
        }

        // Load the lab service for billing reversal
        $labOrder->load('labService');

        // Find and void the associated billing charge
        $this->voidLabOrderCharge($labOrder);

        // Delete the lab order (only if status is 'ordered')
        $labOrder->delete();

        return back()->with('success', 'Lab order removed and billing charge voided.');
    }

    /**
     * Void the billing charge associated with a lab order
     */
    private function voidLabOrderCharge($labOrder): void
    {
        // Get the patient check-in for billing
        $checkin = null;

        if ($labOrder->orderable_type === \App\Models\Consultation::class) {
            $labOrder->loadMissing('orderable');
            $checkin = $labOrder->orderable?->patientCheckin;
        } elseif ($labOrder->orderable_type === \App\Models\WardRound::class) {
            $labOrder->loadMissing('orderable.patientAdmission.consultation.patientCheckin');
            $checkin = $labOrder->orderable?->patientAdmission?->consultation?->patientCheckin;
        }

        if (! $checkin) {
            return;
        }

        // Find the charge for this lab test
        $charge = \App\Models\Charge::where('patient_checkin_id', $checkin->id)
            ->where('service_type', 'laboratory')
            ->where('service_code', $labOrder->labService->code)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($charge) {
            // Mark charge as voided instead of deleting
            $charge->update([
                'status' => 'voided',
                'notes' => 'Lab order cancelled before sample collection - voided by '.auth()->user()->name,
            ]);
        }
    }

    public function complete(StoreWardRoundRequest $request, PatientAdmission $admission, WardRound $wardRound)
    {
        $this->authorize('update', $wardRound);

        // Only allow completing in-progress ward rounds
        if ($wardRound->status !== 'in_progress') {
            return redirect()->back()->with('error', 'Ward round is already completed.');
        }

        $validated = $request->validated();

        // Update ward round status to completed
        $wardRound->update([
            'status' => 'completed',
            'round_datetime' => $validated['round_datetime'] ?? $wardRound->round_datetime,
            'presenting_complaint' => $validated['presenting_complaint'] ?? $wardRound->presenting_complaint,
            'history_presenting_complaint' => $validated['history_presenting_complaint'] ?? $wardRound->history_presenting_complaint,
            'on_direct_questioning' => $validated['on_direct_questioning'] ?? $wardRound->on_direct_questioning,
            'examination_findings' => $validated['examination_findings'] ?? $wardRound->examination_findings,
            'assessment_notes' => $validated['assessment_notes'] ?? $wardRound->assessment_notes,
            'plan_notes' => $validated['plan_notes'] ?? $wardRound->plan_notes,
        ]);

        // Update patient medical histories if provided
        $patient = $admission->patient;
        if (isset($validated['past_medical_surgical_history'])) {
            $patient->past_medical_surgical_history = $validated['past_medical_surgical_history'];
        }
        if (isset($validated['drug_history'])) {
            $patient->drug_history = $validated['drug_history'];
        }
        if (isset($validated['family_history'])) {
            $patient->family_history = $validated['family_history'];
        }
        if (isset($validated['social_history'])) {
            $patient->social_history = $validated['social_history'];
        }
        if ($patient->isDirty()) {
            $patient->save();
        }

        return redirect()
            ->route('wards.patients.show', ['ward' => $admission->ward_id, 'admission' => $admission->id])
            ->with('success', 'Ward round completed successfully.');
    }

    public function update(StoreWardRoundRequest $request, PatientAdmission $admission, WardRound $wardRound)
    {
        $this->authorize('update', $wardRound);

        $validated = $request->validated();

        $wardRound->update([
            'progress_note' => $validated['progress_note'],
            'patient_status' => $validated['patient_status'],
            'clinical_impression' => $validated['clinical_impression'] ?? $wardRound->clinical_impression,
            'plan' => $validated['plan'] ?? $wardRound->plan,
            'round_datetime' => $validated['round_datetime'] ?? $wardRound->round_datetime,
        ]);

        $wardRound->load('doctor:id,name');

        return redirect()->back()->with('success', 'Ward round updated successfully.');
    }

    public function destroy(PatientAdmission $admission, WardRound $wardRound)
    {
        $this->authorize('delete', $wardRound);

        $wardRound->delete();

        return redirect()->back()->with('success', 'Ward round deleted successfully.');
    }

    /**
     * Calculate day number based on admission date
     */
    private function calculateDayNumber(PatientAdmission $admission): int
    {
        $admissionDate = \Carbon\Carbon::parse($admission->admitted_at)->startOfDay();
        $today = \Carbon\Carbon::today();

        return $admissionDate->diffInDays($today) + 1;
    }
}
