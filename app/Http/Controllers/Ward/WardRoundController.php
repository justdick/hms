<?php

namespace App\Http\Controllers\Ward;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWardRoundRequest;
use App\Models\PatientAdmission;
use App\Models\WardRound;

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

    public function store(StoreWardRoundRequest $request, PatientAdmission $admission)
    {
        $this->authorize('create', WardRound::class);

        $validated = $request->validated();

        $wardRound = $admission->wardRounds()->create([
            'doctor_id' => auth()->id(),
            'progress_note' => $validated['progress_note'],
            'patient_status' => $validated['patient_status'],
            'clinical_impression' => $validated['clinical_impression'] ?? null,
            'plan' => $validated['plan'] ?? null,
            'round_datetime' => $validated['round_datetime'] ?? now(),
        ]);

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
