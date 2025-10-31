<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientRequest;
use App\Models\Patient;
use App\Models\PatientInsurance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function store(StorePatientRequest $request)
    {
        $this->authorize('create', Patient::class);

        return DB::transaction(function () use ($request) {
            $validated = $request->validated();

            // Separate patient data from insurance data
            $patientData = [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'gender' => $validated['gender'],
                'date_of_birth' => $validated['date_of_birth'],
                'phone_number' => $validated['phone_number'] ?? null,
                'address' => $validated['address'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                'national_id' => $validated['national_id'] ?? null,
            ];

            // Generate patient number
            $patientData['patient_number'] = $this->generatePatientNumber();

            // Create patient
            $patient = Patient::create($patientData);

            // Create insurance record if patient has insurance
            if ($request->boolean('has_insurance')) {
                $insuranceData = [
                    'patient_id' => $patient->id,
                    'insurance_plan_id' => $validated['insurance_plan_id'],
                    'membership_id' => $validated['membership_id'],
                    'policy_number' => $validated['policy_number'] ?? null,
                    'card_number' => $validated['card_number'] ?? null,
                    'is_dependent' => $request->boolean('is_dependent'),
                    'principal_member_name' => $validated['principal_member_name'] ?? null,
                    'relationship_to_principal' => $validated['relationship_to_principal'] ?? null,
                    'coverage_start_date' => $validated['coverage_start_date'],
                    'coverage_end_date' => $validated['coverage_end_date'] ?? null,
                    'status' => 'active',
                ];

                PatientInsurance::create($insuranceData);
            }

            return back()->with([
                'patient' => [
                    'id' => $patient->id,
                    'patient_number' => $patient->patient_number,
                    'full_name' => $patient->full_name,
                    'age' => $patient->age,
                    'gender' => $patient->gender,
                    'phone_number' => $patient->phone_number,
                    'has_checkin_today' => false,
                ],
            ]);
        });
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
