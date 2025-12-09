<?php

namespace App\Http\Controllers\Consultation;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\ConsultationDiagnosis;
use App\Models\Diagnosis;
use Illuminate\Http\Request;

class DiagnosisController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        // Use FULLTEXT search for better performance with 12,500+ diagnoses
        // Fall back to LIKE if FULLTEXT doesn't return results (for partial matches)
        $diagnoses = Diagnosis::query()
            ->whereFullText(['diagnosis', 'icd_10'], $query)
            ->orderBy('diagnosis')
            ->limit(20)
            ->get(['id', 'diagnosis as name', 'icd_10 as icd_code']);

        // If no FULLTEXT results, fall back to LIKE for partial matching
        if ($diagnoses->isEmpty()) {
            $diagnoses = Diagnosis::query()
                ->where('diagnosis', 'like', "{$query}%")
                ->orWhere('icd_10', 'like', "{$query}%")
                ->orderBy('diagnosis')
                ->limit(20)
                ->get(['id', 'diagnosis as name', 'icd_10 as icd_code']);
        }

        return response()->json($diagnoses);
    }

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
