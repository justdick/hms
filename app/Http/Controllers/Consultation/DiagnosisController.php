<?php

namespace App\Http\Controllers\Consultation;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\ConsultationDiagnosis;
use App\Models\Diagnosis;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DiagnosisController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        // Strategy: Prioritize phrase matches over individual word matches
        // 1. First, try exact phrase match (LIKE '%phrase%')
        // 2. Then, try starts-with match
        // 3. Finally, use FULLTEXT for broader matches
        // Results are ordered by relevance: exact > starts-with > contains phrase > word matches

        $normalizedQuery = trim(strtolower($query));

        // Build a combined query with relevance scoring
        $diagnoses = Diagnosis::query()
            ->where(function ($q) use ($normalizedQuery) {
                // Match diagnosis containing the search phrase
                $q->whereRaw('LOWER(diagnosis) LIKE ?', ["%{$normalizedQuery}%"])
                    ->orWhereRaw('LOWER(icd_10) LIKE ?', ["%{$normalizedQuery}%"]);
            })
            ->orderByRaw('
                CASE
                    WHEN LOWER(diagnosis) = ? THEN 1
                    WHEN LOWER(diagnosis) LIKE ? THEN 2
                    WHEN LOWER(diagnosis) LIKE ? THEN 3
                    ELSE 4
                END
            ', [$normalizedQuery, "{$normalizedQuery}%", "%{$normalizedQuery}%"])
            ->orderBy('diagnosis')
            ->limit(20)
            ->get(['id', 'diagnosis as name', 'icd_10 as icd_code', 'is_custom']);

        // If no phrase matches found, fall back to FULLTEXT for word-based search
        if ($diagnoses->isEmpty()) {
            try {
                $diagnoses = Diagnosis::query()
                    ->whereFullText(['diagnosis', 'icd_10'], $query)
                    ->orderBy('diagnosis')
                    ->limit(20)
                    ->get(['id', 'diagnosis as name', 'icd_10 as icd_code', 'is_custom']);
            } catch (\RuntimeException $e) {
                // FULLTEXT not supported (e.g., SQLite in tests)
            }
        }

        return response()->json($diagnoses);
    }

    public function storeCustom(Request $request)
    {
        $validated = $request->validate([
            'diagnosis' => 'required|string|max:255|unique:diagnoses,diagnosis',
            'icd_10' => 'required|string|max:20',
        ]);

        $diagnosis = Diagnosis::create([
            'diagnosis' => $validated['diagnosis'],
            'code' => 'CUSTOM-'.strtoupper(Str::random(8)),
            'icd_10' => strtoupper($validated['icd_10']),
            'is_custom' => true,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'id' => $diagnosis->id,
            'name' => $diagnosis->diagnosis,
            'icd_code' => $diagnosis->icd_10,
            'is_custom' => true,
        ]);
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
