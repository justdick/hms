<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
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

    public function index(Request $request)
    {
        $this->authorize('viewAny', Patient::class);

        $query = Patient::query()
            ->with([
                'activeInsurance.plan',
                'checkins' => function ($query) {
                    // Get the most recent incomplete check-in
                    $query->whereIn('status', ['checked_in', 'vitals_taken', 'awaiting_consultation', 'in_consultation'])
                        ->latest()
                        ->limit(1);
                },
            ])
            ->where('status', 'active');

        // Apply search filter if provided
        if ($search = $request->get('search')) {
            $query->search($search);
        }

        $patients = $query->latest()
            ->get()
            ->map(function ($patient) {
                $recentCheckin = $patient->checkins->first();

                return [
                    'id' => $patient->id,
                    'patient_number' => $patient->patient_number,
                    'full_name' => $patient->full_name,
                    'first_name' => $patient->first_name,
                    'last_name' => $patient->last_name,
                    'age' => $patient->age,
                    'gender' => $patient->gender,
                    'phone_number' => $patient->phone_number,
                    'date_of_birth' => $patient->date_of_birth->format('Y-m-d'),
                    'address' => $patient->address,
                    'status' => $patient->status,
                    'active_insurance' => $patient->activeInsurance ? [
                        'id' => $patient->activeInsurance->id,
                        'insurance_plan' => [
                            'id' => $patient->activeInsurance->plan->id,
                            'name' => $patient->activeInsurance->plan->name,
                        ],
                        'membership_id' => $patient->activeInsurance->membership_id,
                        'coverage_start_date' => $patient->activeInsurance->coverage_start_date->format('Y-m-d'),
                        'coverage_end_date' => $patient->activeInsurance->coverage_end_date?->format('Y-m-d'),
                    ] : null,
                    'recent_checkin' => $recentCheckin ? [
                        'id' => $recentCheckin->id,
                        'checked_in_at' => $recentCheckin->checked_in_at->format('Y-m-d H:i'),
                        'status' => $recentCheckin->status,
                    ] : null,
                ];
            });

        // Get active departments for check-in modal
        $departments = \App\Models\Department::active()
            ->orderBy('name')
            ->get()
            ->map(function ($department) {
                return [
                    'id' => $department->id,
                    'name' => $department->name,
                    'code' => $department->code,
                    'description' => $department->description,
                ];
            });

        // Get insurance plans for registration modal
        $insurancePlans = \App\Models\InsurancePlan::with('provider')
            ->where('is_active', true)
            ->orderBy('plan_name')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'plan_name' => $plan->plan_name,
                    'plan_code' => $plan->plan_code,
                    'provider' => [
                        'id' => $plan->provider->id,
                        'name' => $plan->provider->name,
                        'code' => $plan->provider->code,
                    ],
                ];
            });

        return inertia('Patients/Index', [
            'patients' => [
                'data' => $patients,
            ],
            'departments' => $departments,
            'insurancePlans' => $insurancePlans,
        ]);
    }

    public function show(Patient $patient)
    {
        $this->authorize('view', $patient);

        $patient->load([
            'activeInsurance.plan',
            'insurancePlans.plan',
            'checkins' => function ($query) {
                $query->with('department')->latest()->limit(10);
            },
        ]);

        // Get active departments for check-in modal
        $departments = \App\Models\Department::active()
            ->orderBy('name')
            ->get()
            ->map(function ($department) {
                return [
                    'id' => $department->id,
                    'name' => $department->name,
                    'code' => $department->code,
                    'description' => $department->description,
                ];
            });

        // Check if user can view medical history
        $canViewMedicalHistory = auth()->user()->can('viewMedicalHistory', $patient);

        // Get billing summary if user has permission
        $billingSummary = null;
        if (auth()->user()->can('patients.view')) {
            $billingSummary = $this->getBillingSummary($patient);
        }

        return inertia('Patients/Show', [
            'patient' => [
                'id' => $patient->id,
                'patient_number' => $patient->patient_number,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'full_name' => $patient->full_name,
                'gender' => $patient->gender,
                'date_of_birth' => $patient->date_of_birth->format('Y-m-d'),
                'age' => $patient->age,
                'phone_number' => $patient->phone_number,
                'address' => $patient->address,
                'emergency_contact_name' => $patient->emergency_contact_name,
                'emergency_contact_phone' => $patient->emergency_contact_phone,
                'national_id' => $patient->national_id,
                'status' => $patient->status,
                'is_credit_eligible' => $patient->is_credit_eligible,
                'credit_reason' => $patient->credit_reason,
                'past_medical_surgical_history' => $canViewMedicalHistory ? $patient->past_medical_surgical_history : null,
                'drug_history' => $canViewMedicalHistory ? $patient->drug_history : null,
                'family_history' => $canViewMedicalHistory ? $patient->family_history : null,
                'social_history' => $canViewMedicalHistory ? $patient->social_history : null,
                'active_insurance' => $patient->activeInsurance ? [
                    'id' => $patient->activeInsurance->id,
                    'insurance_plan' => [
                        'id' => $patient->activeInsurance->plan->id,
                        'name' => $patient->activeInsurance->plan->name,
                    ],
                    'membership_id' => $patient->activeInsurance->membership_id,
                    'policy_number' => $patient->activeInsurance->policy_number,
                    'card_number' => $patient->activeInsurance->card_number,
                    'is_dependent' => $patient->activeInsurance->is_dependent,
                    'principal_member_name' => $patient->activeInsurance->principal_member_name,
                    'relationship_to_principal' => $patient->activeInsurance->relationship_to_principal,
                    'coverage_start_date' => $patient->activeInsurance->coverage_start_date->format('Y-m-d'),
                    'coverage_end_date' => $patient->activeInsurance->coverage_end_date?->format('Y-m-d'),
                    'status' => $patient->activeInsurance->status,
                ] : null,
                'insurance_plans' => $patient->insurancePlans->map(function ($insurance) {
                    return [
                        'id' => $insurance->id,
                        'insurance_plan' => [
                            'id' => $insurance->plan->id,
                            'name' => $insurance->plan->name,
                        ],
                        'membership_id' => $insurance->membership_id,
                        'coverage_start_date' => $insurance->coverage_start_date->format('Y-m-d'),
                        'coverage_end_date' => $insurance->coverage_end_date?->format('Y-m-d'),
                        'status' => $insurance->status,
                    ];
                }),
                'checkin_history' => $patient->checkins->map(function ($checkin) {
                    return [
                        'id' => $checkin->id,
                        'checked_in_at' => $checkin->checked_in_at->format('Y-m-d H:i'),
                        'department' => [
                            'id' => $checkin->department->id,
                            'name' => $checkin->department->name,
                        ],
                        'status' => $checkin->status,
                    ];
                }),
            ],
            'departments' => $departments,
            'can_edit' => auth()->user()->can('update', $patient),
            'can_checkin' => auth()->user()->can('create', \App\Models\PatientCheckin::class),
            'can_view_medical_history' => $canViewMedicalHistory,
            'billing_summary' => $billingSummary,
            'can_process_payment' => auth()->user()->can('billing.create'),
            'can_manage_credit' => auth()->user()->can('billing.manage-credit'),
        ]);
    }

    public function edit(Patient $patient)
    {
        $this->authorize('update', $patient);

        $patient->load(['activeInsurance.plan']);

        // Get insurance plans
        $insurancePlans = \App\Models\InsurancePlan::with('provider')
            ->where('is_active', true)
            ->orderBy('plan_name')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'plan_name' => $plan->plan_name,
                    'plan_code' => $plan->plan_code,
                    'provider' => [
                        'id' => $plan->provider->id,
                        'name' => $plan->provider->name,
                        'code' => $plan->provider->code,
                    ],
                ];
            });

        return inertia('Patients/Edit', [
            'patient' => [
                'id' => $patient->id,
                'patient_number' => $patient->patient_number,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'full_name' => $patient->full_name,
                'gender' => $patient->gender,
                'date_of_birth' => $patient->date_of_birth->format('Y-m-d'),
                'age' => $patient->age,
                'phone_number' => $patient->phone_number,
                'address' => $patient->address,
                'emergency_contact_name' => $patient->emergency_contact_name,
                'emergency_contact_phone' => $patient->emergency_contact_phone,
                'national_id' => $patient->national_id,
                'status' => $patient->status,
                'past_medical_surgical_history' => $patient->past_medical_surgical_history,
                'drug_history' => $patient->drug_history,
                'family_history' => $patient->family_history,
                'social_history' => $patient->social_history,
                'active_insurance' => $patient->activeInsurance ? [
                    'id' => $patient->activeInsurance->id,
                    'insurance_plan_id' => $patient->activeInsurance->insurance_plan_id,
                    'membership_id' => $patient->activeInsurance->membership_id,
                    'policy_number' => $patient->activeInsurance->policy_number,
                    'card_number' => $patient->activeInsurance->card_number,
                    'is_dependent' => $patient->activeInsurance->is_dependent,
                    'principal_member_name' => $patient->activeInsurance->principal_member_name,
                    'relationship_to_principal' => $patient->activeInsurance->relationship_to_principal,
                    'coverage_start_date' => $patient->activeInsurance->coverage_start_date->format('Y-m-d'),
                    'coverage_end_date' => $patient->activeInsurance->coverage_end_date?->format('Y-m-d'),
                ] : null,
            ],
            'insurance_plans' => $insurancePlans,
        ]);
    }

    public function update(UpdatePatientRequest $request, Patient $patient)
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($request, $patient, $validated) {
            // Update patient data
            $patient->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'gender' => $validated['gender'],
                'date_of_birth' => $validated['date_of_birth'],
                'phone_number' => $validated['phone_number'] ?? null,
                'address' => $validated['address'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                'national_id' => $validated['national_id'] ?? null,
                'past_medical_surgical_history' => $validated['past_medical_surgical_history'] ?? null,
                'drug_history' => $validated['drug_history'] ?? null,
                'family_history' => $validated['family_history'] ?? null,
                'social_history' => $validated['social_history'] ?? null,
            ]);

            // Update or create insurance record if patient has insurance
            if ($request->boolean('has_insurance')) {
                $insuranceData = [
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

                // Update existing active insurance or create new one
                $activeInsurance = $patient->activeInsurance;
                if ($activeInsurance) {
                    $activeInsurance->update($insuranceData);
                } else {
                    $patient->insurancePlans()->create($insuranceData);
                }
            }

            return redirect()
                ->route('patients.show', $patient)
                ->with('success', 'Patient information updated successfully.');
        });
    }

    /**
     * Get billing summary for a patient
     */
    private function getBillingSummary(Patient $patient): ?array
    {
        $checkins = $patient->checkins()
            ->with(['charges' => function ($query) {
                $query->where('status', 'pending');
            }])
            ->get();

        $pendingCharges = $checkins->flatMap->charges
            ->where('status', 'pending');

        if ($pendingCharges->isEmpty()) {
            // Get recent payments if no pending charges
            $recentPayments = $patient->checkins()
                ->with(['charges' => function ($query) {
                    $query->where('status', 'paid')
                        ->whereNotNull('paid_at')
                        ->latest('paid_at')
                        ->limit(5);
                }])
                ->get()
                ->flatMap->charges
                ->sortByDesc('paid_at')
                ->take(5);

            return [
                'total_outstanding' => 0,
                'insurance_covered' => 0,
                'patient_owes' => 0,
                'recent_payments' => $recentPayments->map(fn ($charge) => [
                    'date' => $charge->paid_at->format('M j, Y'),
                    'amount' => $charge->paid_amount ?? $charge->amount,
                    'method' => $charge->metadata['payment_method'] ?? 'Unknown',
                    'description' => $charge->description,
                ])->values(),
                'has_active_overrides' => false,
            ];
        }

        // Get recent payments
        $recentPayments = $patient->checkins()
            ->with(['charges' => function ($query) {
                $query->where('status', 'paid')
                    ->whereNotNull('paid_at')
                    ->latest('paid_at')
                    ->limit(5);
            }])
            ->get()
            ->flatMap->charges
            ->sortByDesc('paid_at')
            ->take(5);

        // Check for active overrides
        $hasActiveOverrides = \App\Models\ServiceAccessOverride::whereIn(
            'patient_checkin_id',
            $checkins->pluck('id')
        )
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->exists();

        return [
            'total_outstanding' => $pendingCharges->sum('amount'),
            'insurance_covered' => $pendingCharges->sum('insurance_covered_amount'),
            'patient_owes' => $pendingCharges->sum('patient_copay_amount'),
            'recent_payments' => $recentPayments->map(fn ($charge) => [
                'date' => $charge->paid_at->format('M j, Y'),
                'amount' => $charge->paid_amount ?? $charge->amount,
                'method' => $charge->metadata['payment_method'] ?? 'Unknown',
                'description' => $charge->description,
            ])->values(),
            'has_active_overrides' => $hasActiveOverrides,
        ];
    }

    private function generatePatientNumber(): string
    {
        // Get configuration from system settings
        $format = \App\Models\SystemConfiguration::get('patient_number_format', 'prefix_year_number');
        $prefix = \App\Models\SystemConfiguration::get('patient_number_prefix', 'PAT');
        $yearFormat = \App\Models\SystemConfiguration::get('patient_number_year_format', 'YYYY');
        $separator = \App\Models\SystemConfiguration::get('patient_number_separator', '');
        $padding = (int) \App\Models\SystemConfiguration::get('patient_number_padding', 6);
        $resetPolicy = \App\Models\SystemConfiguration::get('patient_number_reset', 'never');

        // Generate year based on format
        $year = $yearFormat === 'YYYY' ? date('Y') : date('y');

        // Build the search pattern based on format and reset policy
        if ($format === 'number_year') {
            // Format: 1495/2022 - number comes first
            $suffixPattern = $separator.$year;

            // For reset policies with number_year format
            if ($resetPolicy === 'monthly') {
                $suffixPattern = $separator.date('m').$separator.$year;
            }

            // Find the last patient number with this year suffix
            $lastPatient = Patient::where('patient_number', 'like', "%{$suffixPattern}")
                ->orderBy('id', 'desc')
                ->first();

            if ($lastPatient) {
                // Extract the numeric part from the beginning
                $numericPart = preg_replace('/[^0-9].*/', '', $lastPatient->patient_number);
                $lastNumber = (int) $numericPart;
                $newNumber = $lastNumber + 1;
            } else {
                $newNumber = 1;
            }

            $paddedNumber = str_pad($newNumber, $padding, '0', STR_PAD_LEFT);

            if ($resetPolicy === 'monthly') {
                return $paddedNumber.$separator.date('m').$separator.$year;
            }

            return $paddedNumber.$separator.$year;
        }

        // Default format: PAT2025000001 - prefix + year + number
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
