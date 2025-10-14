<?php

namespace App\Http\Controllers\Consultation;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\ConsultationDiagnosis;
use App\Models\Diagnosis;
use Illuminate\Http\Request;

class DiagnosisController extends Controller
{
    public function store(Request $request, Consultation $consultation)
    {
        $this->authorize('update', $consultation);

        $request->validate([
            'diagnosis_id' => 'required|exists:diagnoses,id',
            'type' => 'required|in:provisional,principal',
        ]);

        // Check if this diagnosis already exists for this consultation
        $exists = $consultation->diagnoses()
            ->where('diagnosis_id', $request->diagnosis_id)
            ->where('type', $request->type)
            ->exists();

        if ($exists) {
            return back()->with('error', 'This diagnosis has already been added.');
        }

        $diagnosis = $consultation->diagnoses()->create([
            'diagnosis_id' => $request->diagnosis_id,
            'type' => $request->type,
        ]);

        return back()->with('success', 'Diagnosis added successfully.');
    }

    public function update(Request $request, Consultation $consultation, ConsultationDiagnosis $diagnosis)
    {
        $this->authorize('update', $consultation);

        $request->validate([
            'diagnosis_id' => 'required|exists:diagnoses,id',
            'type' => 'required|in:provisional,principal',
        ]);

        $diagnosis->update([
            'diagnosis_id' => $request->diagnosis_id,
            'type' => $request->type,
        ]);

        return back()->with('success', 'Diagnosis updated successfully.');
    }

    public function destroy(Consultation $consultation, ConsultationDiagnosis $diagnosis)
    {
        $this->authorize('update', $consultation);

        $diagnosis->delete();

        return back()->with('success', 'Diagnosis deleted successfully.');
    }
}
