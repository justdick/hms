<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function search(Request $request)
    {
        $this->authorize('viewAny', Patient::class);

        $search = $request->get('search');

        if (empty($search)) {
            return response()->json(['patients' => []]);
        }

        $patients = Patient::search($search)
            ->where('status', 'active')
            ->with(['checkins' => function ($query) {
                $query->today()->latest();
            }])
            ->limit(10)
            ->get()
            ->map(function ($patient) {
                return [
                    'id' => $patient->id,
                    'patient_number' => $patient->patient_number,
                    'full_name' => $patient->full_name,
                    'age' => $patient->age,
                    'gender' => $patient->gender,
                    'phone_number' => $patient->phone_number,
                    'last_visit' => $patient->checkins->first()?->checked_in_at,
                    'has_checkin_today' => $patient->checkins->isNotEmpty(),
                ];
            });

        return response()->json(['patients' => $patients]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Patient::class);

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'gender' => 'required|in:male,female',
            'date_of_birth' => 'required|date|before:today',
            'phone_number' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'national_id' => 'nullable|string|max:50',
        ]);

        // Generate patient number
        $validated['patient_number'] = $this->generatePatientNumber();

        $patient = Patient::create($validated);

        return response()->json([
            'patient' => [
                'id' => $patient->id,
                'patient_number' => $patient->patient_number,
                'full_name' => $patient->full_name,
                'age' => $patient->age,
                'gender' => $patient->gender,
                'phone_number' => $patient->phone_number,
                'has_checkin_today' => false,
            ],
            'message' => 'Patient registered successfully',
        ]);
    }

    public function show(Patient $patient)
    {
        $this->authorize('view', $patient);

        $patient->load([
            'checkins.department',
            'vitalSigns' => function ($query) {
                $query->latest()->limit(5);
            },
        ]);

        return response()->json(['patient' => $patient]);
    }

    private function generatePatientNumber(): string
    {
        $year = date('Y');
        $prefix = "PAT{$year}";

        $lastPatient = Patient::where('patient_number', 'like', "{$prefix}%")
            ->orderBy('patient_number', 'desc')
            ->first();

        if ($lastPatient) {
            $lastNumber = (int) substr($lastPatient->patient_number, -6);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix.str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }
}
