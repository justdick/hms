<?php

namespace App\Http\Controllers\Consultation;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\ConsultationDiagnosis;
use Illuminate\Http\Request;

class DiagnosisController extends Controller
{
    public function store(Request $request, Consultation $consultation)
    {
        // Ensure this doctor has access to the patient's department
        if (! $consultation->patientCheckin->department->users()->where('users.id', $request->user()->id)->exists()) {
            abort(403, 'You do not have access to patients in this department.');
        }

        $request->validate([
            'icd_code' => 'required|string|max:10',
            'diagnosis_description' => 'required|string|max:500',
            'is_primary' => 'boolean',
        ]);

        // If this is being marked as primary, unmark any existing primary diagnosis
        if ($request->is_primary) {
            $consultation->diagnoses()->update(['is_primary' => false]);
        }

        $diagnosis = $consultation->diagnoses()->create([
            'icd_code' => $request->icd_code,
            'diagnosis_description' => $request->diagnosis_description,
            'is_primary' => $request->is_primary ?? false,
        ]);

        return response()->json([
            'diagnosis' => $diagnosis,
            'message' => 'Diagnosis added successfully.',
        ]);
    }

    public function update(Request $request, Consultation $consultation, ConsultationDiagnosis $diagnosis)
    {
        // Ensure this doctor has access to the patient's department
        if (! $consultation->patientCheckin->department->users()->where('users.id', $request->user()->id)->exists()) {
            abort(403, 'You do not have access to patients in this department.');
        }

        $request->validate([
            'icd_code' => 'required|string|max:10',
            'diagnosis_description' => 'required|string|max:500',
            'is_primary' => 'boolean',
        ]);

        // If this is being marked as primary, unmark any existing primary diagnosis
        if ($request->is_primary && ! $diagnosis->is_primary) {
            $consultation->diagnoses()->where('id', '!=', $diagnosis->id)->update(['is_primary' => false]);
        }

        $diagnosis->update([
            'icd_code' => $request->icd_code,
            'diagnosis_description' => $request->diagnosis_description,
            'is_primary' => $request->is_primary ?? false,
        ]);

        return response()->json([
            'diagnosis' => $diagnosis,
            'message' => 'Diagnosis updated successfully.',
        ]);
    }

    public function destroy(Consultation $consultation, ConsultationDiagnosis $diagnosis)
    {
        // Ensure this doctor has access to the patient's department
        if (! $consultation->patientCheckin->department->users()->where('users.id', request()->user()->id)->exists()) {
            abort(403, 'You do not have access to patients in this department.');
        }

        $diagnosis->delete();

        return response()->json([
            'message' => 'Diagnosis deleted successfully.',
        ]);
    }

    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:3',
        ]);

        // Mock ICD-10 search - in real implementation, this would query an ICD-10 database
        $mockIcdCodes = [
            ['code' => 'I20.9', 'description' => 'Angina pectoris, unspecified'],
            ['code' => 'I21.9', 'description' => 'Acute myocardial infarction, unspecified'],
            ['code' => 'E78.5', 'description' => 'Hyperlipidemia, unspecified'],
            ['code' => 'I10', 'description' => 'Essential hypertension'],
            ['code' => 'E11.9', 'description' => 'Type 2 diabetes mellitus without complications'],
            ['code' => 'J06.9', 'description' => 'Acute upper respiratory infection, unspecified'],
            ['code' => 'R50.9', 'description' => 'Fever, unspecified'],
            ['code' => 'L03.90', 'description' => 'Cellulitis, unspecified'],
            ['code' => 'K21.9', 'description' => 'Gastro-esophageal reflux disease without esophagitis'],
            ['code' => 'M25.50', 'description' => 'Pain in unspecified joint'],
            ['code' => 'R06.02', 'description' => 'Shortness of breath'],
            ['code' => 'R51.9', 'description' => 'Headache, unspecified'],
            ['code' => 'K59.00', 'description' => 'Constipation, unspecified'],
            ['code' => 'R11.10', 'description' => 'Vomiting, unspecified'],
            ['code' => 'Z00.00', 'description' => 'Encounter for general adult medical examination without abnormal findings'],
        ];

        $query = strtolower($request->query);

        $filteredCodes = array_filter($mockIcdCodes, function ($code) use ($query) {
            return str_contains(strtolower($code['code']), $query) ||
                   str_contains(strtolower($code['description']), $query);
        });

        return response()->json([
            'icd_codes' => array_values($filteredCodes),
        ]);
    }
}
