<?php

namespace App\Http\Controllers\Ward;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWardRoundRequest;
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

        $dayNumber = $admission->wardRounds()->count() + 1;

        return Inertia::render('Ward/WardRoundCreate', [
            'admission' => $admission,
            'dayNumber' => $dayNumber,
            'encounterType' => 'ward_round',
        ]);
    }

    public function store(StoreWardRoundRequest $request, PatientAdmission $admission)
    {
        $this->authorize('create', WardRound::class);

        $validated = $request->validated();

        // Auto-calculate day number
        $dayNumber = $admission->wardRounds()->count() + 1;

        $wardRound = $admission->wardRounds()->create([
            'doctor_id' => auth()->id(),
            'day_number' => $dayNumber,
            'round_type' => $validated['round_type'] ?? 'daily_round',
            'presenting_complaint' => $validated['presenting_complaint'] ?? null,
            'history_presenting_complaint' => $validated['history_presenting_complaint'] ?? null,
            'on_direct_questioning' => $validated['on_direct_questioning'] ?? null,
            'examination_findings' => $validated['examination_findings'] ?? null,
            'assessment_notes' => $validated['assessment_notes'] ?? null,
            'plan_notes' => $validated['plan_notes'] ?? null,
            'patient_status' => $validated['patient_status'] ?? 'stable',
            'round_datetime' => $validated['round_datetime'] ?? now(),
        ]);

        // Handle lab orders if provided
        if (! empty($validated['lab_orders'])) {
            foreach ($validated['lab_orders'] as $labOrder) {
                $wardRound->labOrders()->create($labOrder);
            }
        }

        // Handle prescriptions if provided
        if (! empty($validated['prescriptions'])) {
            foreach ($validated['prescriptions'] as $prescription) {
                $wardRound->prescriptions()->create($prescription);
            }
        }

        // Handle diagnoses if provided
        if (! empty($validated['diagnoses'])) {
            foreach ($validated['diagnoses'] as $diagnosis) {
                $admission->diagnoses()->create([
                    'icd_code' => $diagnosis['icd_code'],
                    'icd_version' => $diagnosis['icd_version'],
                    'diagnosis_name' => $diagnosis['diagnosis_name'],
                    'diagnosis_type' => $diagnosis['diagnosis_type'] ?? 'working',
                    'source_type' => WardRound::class,
                    'source_id' => $wardRound->id,
                    'diagnosed_by' => auth()->id(),
                    'diagnosed_at' => now(),
                    'clinical_notes' => $diagnosis['clinical_notes'] ?? null,
                ]);
            }
        }

        $wardRound->load('doctor:id,name');

        return redirect()->back()->with('success', 'Ward round recorded successfully.');
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
}
