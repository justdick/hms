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
                // Show incomplete check-ins regardless of date
                $query->whereIn('status', ['checked_in', 'vitals_taken', 'awaiting_consultation', 'in_consultation'])
                    ->latest();
            }])
            ->limit(10)
            ->get()
            ->map(function ($patient) {
                $incompleteCheckin = $patient->checkins->first();

                return [
                    'id' => $patient->id,
                    'patient_number' => $patient->patient_number,
                    'full_name' => $patient->full_name,
                    'age' => $patient->age,
                    'gender' => $patient->gender,
                    'phone_number' => $patient->phone_number,
                    'last_visit' => $incompleteCheckin?->checked_in_at,
                    'has_incomplete_checkin' => $incompleteCheckin !== null,
                    'incomplete_checkin_status' => $incompleteCheckin?->status,
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
        // Get configuration from system settings
        $prefix = \App\Models\SystemConfiguration::get('patient_number_prefix', 'PAT');
        $yearFormat = \App\Models\SystemConfiguration::get('patient_number_year_format', 'YYYY');
        $separator = \App\Models\SystemConfiguration::get('patient_number_separator', '');
        $padding = \App\Models\SystemConfiguration::get('patient_number_padding', 6);
        $resetPolicy = \App\Models\SystemConfiguration::get('patient_number_reset', 'never');

        // Generate year based on format
        $year = $yearFormat === 'YYYY' ? date('Y') : date('y');

        // Build the prefix pattern based on reset policy
        $basePattern = $prefix.$separator.$year.$separator;

        // For reset policies, we need to check monthly or yearly
        if ($resetPolicy === 'monthly') {
            $basePattern .= date('m').$separator;
        }

        // Find the last patient number with this pattern
        $lastPatient = Patient::where('patient_number', 'like', "{$basePattern}%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastPatient) {
            // Extract the numeric part from the end
            $lastNumber = (int) substr($lastPatient->patient_number, -$padding);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $basePattern.str_pad($newNumber, $padding, '0', STR_PAD_LEFT);
    }
}
