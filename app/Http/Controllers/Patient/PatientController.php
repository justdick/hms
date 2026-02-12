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
                        ->latest('checked_in_at')
                        ->limit(1);
                },
            ])
            ->where('status', 'active');

        // Apply search filter if provided
        if ($search = $request->get('search')) {
            $query->search($search);
        }

        $perPage = $request->get('per_page', 5);
        $paginated = $query->latest()->paginate($perPage)->withQueryString();

        $patients = $paginated->through(function ($patient) {
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
                'active_insurance' => $patient->activeInsurance && $patient->activeInsurance->plan ? [
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
                        'is_nhis' => $plan->provider->is_nhis ?? false,
                    ],
                ];
            });

        // Get NHIS settings for registration modal
        $nhisSettings = \App\Models\NhisSettings::getInstance();
        $nhisCredentials = null;
        if ($nhisSettings->verification_mode === 'extension' && $nhisSettings->nhia_username) {
            try {
                $nhisCredentials = [
                    'username' => $nhisSettings->nhia_username,
                    'password' => $nhisSettings->nhia_password,
                ];
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                // Password was encrypted with different APP_KEY, ignore
            }
        }

        return inertia('Patients/Index', [
            'patients' => $patients,
            'departments' => $departments,
            'insurancePlans' => $insurancePlans,
            'nhisSettings' => [
                'verification_mode' => $nhisSettings->verification_mode,
                'nhia_portal_url' => $nhisSettings->nhia_portal_url,
                'auto_open_portal' => $nhisSettings->auto_open_portal,
                'credentials' => $nhisCredentials,
            ],
            'filters' => [
                'search' => $request->get('search', ''),
            ],
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

        // Get medical history if user has permission
        $medicalHistory = null;
        if ($canViewMedicalHistory) {
            $medicalHistory = $this->getMedicalHistory($patient);
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
                'past_medical_surgical_history' => $canViewMedicalHistory ? $patient->past_medical_surgical_history : null,
                'drug_history' => $canViewMedicalHistory ? $patient->drug_history : null,
                'family_history' => $canViewMedicalHistory ? $patient->family_history : null,
                'social_history' => $canViewMedicalHistory ? $patient->social_history : null,
                'active_insurance' => $patient->activeInsurance && $patient->activeInsurance->plan ? [
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
                'insurance_plans' => $patient->insurancePlans->filter(fn ($insurance) => $insurance->plan !== null)->map(function ($insurance) {
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
                })->values(),
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
            'medical_history' => $medicalHistory,
            'billing_summary' => $billingSummary,
            'can_process_payment' => auth()->user()->can('billing.create'),
            'can_manage_credit' => auth()->user()->can('billing.manage-credit'),
            'payment_methods' => \App\Models\PaymentMethod::where('is_active', true)->get(),
            'account_summary' => $this->getAccountSummary($patient),
            'active_checkin_without_insurance' => $this->getActiveCheckinWithoutInsurance($patient),
            'nhis_settings' => $this->getNhisSettings(),
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
                        'is_nhis' => $plan->provider->is_nhis ?? false,
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
            'nhis_settings' => $this->getNhisSettings(),
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
            $newInsurance = null;
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
                $insuranceWasAdded = ! $activeInsurance;
                if ($activeInsurance) {
                    $activeInsurance->update($insuranceData);
                } else {
                    $patient->insurancePlans()->create($insuranceData);
                }

                // If insurance was newly added, check for active checkin
                if ($insuranceWasAdded) {
                    $activeCheckin = $this->getActiveCheckinWithoutInsurance($patient);
                    if ($activeCheckin) {
                        return redirect()
                            ->route('patients.show', $patient)
                            ->with('success', 'Patient information updated successfully.')
                            ->with('show_apply_insurance_modal', true)
                            ->with('active_checkin_id', $activeCheckin['id']);
                    }
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

    /**
     * Get account summary for a patient
     */
    private function getAccountSummary(Patient $patient): ?array
    {
        $account = $patient->account;

        if (! $account) {
            return [
                'balance' => 0,
                'credit_limit' => 0,
            ];
        }

        return [
            'balance' => (float) $account->balance,
            'credit_limit' => (float) $account->credit_limit,
        ];
    }

    /**
     * Get patient medical history as JSON (for modal display in consultation/ward round pages).
     */
    public function medicalHistoryJson(Patient $patient): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $patient);

        $history = $this->getMedicalHistory($patient);

        // Get admissions with full ward round details (including dedup)
        $admissions = $patient->admissions()
            ->with([
                'ward:id,name',
                'bed:id,bed_number',
                'consultation.doctor:id,name',
                'diagnoses',
                'wardRounds' => fn ($q) => $q->with([
                    'doctor:id,name',
                    'prescriptions.drug:id,name,generic_name,form,strength',
                    'labOrders.labService:id,name,code,is_imaging,test_parameters',
                    'procedures.procedureType:id,name,code',
                    'diagnoses',
                ])->orderByDesc('round_datetime'),
            ])
            ->orderByDesc('admitted_at')
            ->limit(20)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'admission_number' => $a->admission_number,
                'admitted_at' => $a->admitted_at?->format('Y-m-d H:i'),
                'discharged_at' => $a->discharged_at?->format('Y-m-d H:i'),
                'status' => $a->status,
                'ward' => $a->ward?->name,
                'bed' => $a->bed?->bed_number,
                'admission_reason' => $a->admission_reason,
                'discharge_notes' => $a->discharge_notes,
                'admitting_doctor' => $a->consultation?->doctor?->name,
                'diagnoses' => $a->diagnoses->map(fn ($d) => [
                    'type' => $d->diagnosis_type,
                    'code' => $d->icd_code,
                    'description' => $d->diagnosis_name,
                    'is_active' => $d->is_active,
                ]),
                'ward_rounds' => $a->wardRounds
                    ->groupBy(fn ($wr) => $wr->day_number.'-'.$wr->doctor_id.'-'.$wr->round_datetime?->format('Y-m-d'))
                    ->map(function ($group) {
                        $latest = $group->sortByDesc('id')->first();
                        $allPrescriptions = $group->flatMap(fn ($wr) => $wr->prescriptions)->unique('id');
                        $allLabOrders = $group->flatMap(fn ($wr) => $wr->labOrders)->unique('id');
                        $allProcedures = $group->flatMap(fn ($wr) => $wr->procedures)->unique('id');

                        return [
                            'id' => $latest->id,
                            'date' => $latest->round_datetime?->format('Y-m-d H:i'),
                            'doctor' => $latest->doctor?->name,
                            'day_number' => $latest->day_number,
                            'round_type' => $latest->round_type,
                            'presenting_complaint' => $latest->presenting_complaint,
                            'examination_findings' => $latest->examination_findings,
                            'assessment_notes' => $latest->assessment_notes,
                            'plan_notes' => $latest->plan_notes,
                            'patient_status' => $latest->patient_status,
                            'prescriptions' => $allPrescriptions->values()->map(fn ($p) => [
                                'drug_name' => $p->drug?->name ?? $p->medication_name,
                                'generic_name' => $p->drug?->generic_name,
                                'form' => $p->drug?->form ?? $p->dosage_form,
                                'strength' => $p->drug?->strength,
                                'dose_quantity' => $p->dose_quantity,
                                'frequency' => $p->frequency,
                                'duration' => $p->duration,
                                'quantity' => $p->quantity,
                                'instructions' => $p->instructions,
                                'status' => $p->status,
                            ]),
                            'lab_orders' => $allLabOrders->values()->map(fn ($l) => [
                                'id' => $l->id,
                                'service_name' => $l->labService?->name,
                                'code' => $l->labService?->code,
                                'is_imaging' => $l->labService?->is_imaging,
                                'status' => $l->status,
                                'result_values' => $l->result_values,
                                'result_notes' => $l->result_notes,
                                'ordered_at' => $l->ordered_at?->format('Y-m-d H:i'),
                                'result_entered_at' => $l->result_entered_at?->format('Y-m-d H:i'),
                            ]),
                            'procedures' => $allProcedures->values()->map(fn ($p) => [
                                'name' => $p->procedureType?->name,
                                'code' => $p->procedureType?->code,
                                'notes' => $p->comments,
                            ]),
                        ];
                    })
                    ->values(),
            ]);

        return response()->json([
            'patient_name' => $patient->full_name ?? ($patient->first_name.' '.$patient->last_name),
            'background_history' => [
                'past_medical_surgical_history' => $patient->past_medical_surgical_history,
                'drug_history' => $patient->drug_history,
                'family_history' => $patient->family_history,
                'social_history' => $patient->social_history,
            ],
            'medical_history' => [
                'consultations' => $history['consultations'],
                'admissions' => $admissions,
                'minor_procedures' => $history['minor_procedures'],
            ],
        ]);
    }

    /**
     * Get comprehensive medical history for a patient
     */
    private function getMedicalHistory(Patient $patient): array
    {
        // Get consultations with all related data including vitals from the check-in
        $consultations = \App\Models\Consultation::with([
            'doctor:id,name',
            'patientCheckin:id,department_id,checked_in_at',
            'patientCheckin.department:id,name',
            'patientCheckin.vitalSigns' => fn ($q) => $q->with('recordedBy:id,name')->orderByDesc('recorded_at'),
            'diagnoses.diagnosis:id,code,diagnosis',
            'prescriptions.drug:id,name,generic_name,form,strength',
            'labOrders.labService:id,name,code,is_imaging,test_parameters',
            'procedures.procedureType:id,name,code',
        ])
            ->whereHas('patientCheckin', fn ($q) => $q->where('patient_id', $patient->id))
            ->where('status', 'completed')
            ->orderByDesc('started_at')
            ->limit(50)
            ->get()
            ->map(function ($consultation) {
                // Get vitals from the check-in (usually just one, but could be multiple)
                $vitals = $consultation->patientCheckin?->vitalSigns?->first();

                return [
                    'id' => $consultation->id,
                    'date' => $consultation->patientCheckin?->checked_in_at?->format('Y-m-d H:i'),
                    'doctor' => $consultation->doctor?->name,
                    'department' => $consultation->patientCheckin?->department?->name,
                    'presenting_complaint' => $consultation->presenting_complaint,
                    'history_presenting_complaint' => $consultation->history_presenting_complaint,
                    'examination_findings' => $consultation->examination_findings,
                    'assessment_notes' => $consultation->assessment_notes,
                    'plan_notes' => $consultation->plan_notes,
                    // Include vitals from this visit
                    'vitals' => $vitals ? [
                        'blood_pressure' => $vitals->blood_pressure,
                        'temperature' => $vitals->temperature,
                        'pulse_rate' => $vitals->pulse_rate,
                        'respiratory_rate' => $vitals->respiratory_rate,
                        'oxygen_saturation' => $vitals->oxygen_saturation,
                        'weight' => $vitals->weight,
                        'height' => $vitals->height,
                        'bmi' => $vitals->bmi,
                        'recorded_at' => $vitals->recorded_at?->format('Y-m-d H:i'),
                        'recorded_by' => $vitals->recordedBy?->name,
                    ] : null,
                    'diagnoses' => $consultation->diagnoses->map(fn ($d) => [
                        'type' => $d->type,
                        'code' => $d->diagnosis?->code,
                        'description' => $d->diagnosis?->diagnosis,
                        'notes' => $d->notes,
                    ]),
                    'prescriptions' => $consultation->prescriptions->map(fn ($p) => [
                        'drug_name' => $p->drug?->name ?? $p->medication_name,
                        'generic_name' => $p->drug?->generic_name,
                        'form' => $p->drug?->form ?? $p->dosage_form,
                        'strength' => $p->drug?->strength,
                        'dose_quantity' => $p->dose_quantity,
                        'frequency' => $p->frequency,
                        'duration' => $p->duration,
                        'quantity' => $p->quantity,
                        'instructions' => $p->instructions,
                        'status' => $p->status,
                    ]),
                    'lab_orders' => $consultation->labOrders->map(fn ($l) => [
                        'id' => $l->id,
                        'service_name' => $l->labService?->name,
                        'code' => $l->labService?->code,
                        'is_imaging' => $l->labService?->is_imaging,
                        'test_parameters' => $l->labService?->test_parameters,
                        'status' => $l->status,
                        'result_values' => $l->result_values,
                        'result_notes' => $l->result_notes,
                        'ordered_at' => $l->ordered_at?->format('Y-m-d H:i'),
                        'result_entered_at' => $l->result_entered_at?->format('Y-m-d H:i'),
                    ]),
                    'procedures' => $consultation->procedures->map(fn ($p) => [
                        'name' => $p->procedureType?->name,
                        'code' => $p->procedureType?->code,
                        'notes' => $p->notes,
                    ]),
                ];
            });

        // Get vitals history
        $vitals = $patient->vitalSigns()
            ->with('recordedBy:id,name')
            ->orderByDesc('recorded_at')
            ->limit(50)
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'recorded_at' => $v->recorded_at?->format('Y-m-d H:i'),
                'recorded_by' => $v->recordedBy?->name,
                'blood_pressure' => $v->blood_pressure,
                'temperature' => $v->temperature,
                'pulse_rate' => $v->pulse_rate,
                'respiratory_rate' => $v->respiratory_rate,
                'oxygen_saturation' => $v->oxygen_saturation,
                'weight' => $v->weight,
                'height' => $v->height,
                'bmi' => $v->bmi,
                'notes' => $v->notes,
            ]);

        // Get admissions history
        $admissions = $patient->admissions()
            ->with([
                'ward:id,name',
                'bed:id,bed_number',
                'consultation.doctor:id,name',
                'diagnoses',
            ])
            ->orderByDesc('admitted_at')
            ->limit(20)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'admission_number' => $a->admission_number,
                'admitted_at' => $a->admitted_at?->format('Y-m-d H:i'),
                'discharged_at' => $a->discharged_at?->format('Y-m-d H:i'),
                'status' => $a->status,
                'ward' => $a->ward?->name,
                'bed' => $a->bed?->bed_number,
                'admission_reason' => $a->admission_reason,
                'discharge_notes' => $a->discharge_notes,
                'admitting_doctor' => $a->consultation?->doctor?->name,
                'diagnoses' => $a->diagnoses->map(fn ($d) => [
                    'type' => $d->diagnosis_type,
                    'code' => $d->icd_code,
                    'description' => $d->diagnosis_name,
                    'is_active' => $d->is_active,
                ]),
            ]);

        // Get all prescriptions (including from ward rounds)
        $allPrescriptions = \App\Models\Prescription::with([
            'drug:id,name,generic_name,form,strength',
            'prescribable',
        ])
            ->where(function ($query) use ($patient) {
                // Prescriptions from consultations
                $query->whereHasMorph('prescribable', [\App\Models\Consultation::class], function ($q) use ($patient) {
                    $q->whereHas('patientCheckin', fn ($sq) => $sq->where('patient_id', $patient->id));
                })
                // Prescriptions from ward rounds
                    ->orWhereHasMorph('prescribable', [\App\Models\WardRound::class], function ($q) use ($patient) {
                        $q->whereHas('patientAdmission', fn ($sq) => $sq->where('patient_id', $patient->id));
                    });
            })
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(function ($p) {
                // Get the actual date from the consultation or ward round
                $date = null;
                if ($p->prescribable instanceof \App\Models\Consultation) {
                    $date = $p->prescribable->started_at ?? $p->prescribable->created_at;
                } elseif ($p->prescribable instanceof \App\Models\WardRound) {
                    $date = $p->prescribable->round_date ?? $p->prescribable->created_at;
                } else {
                    $date = $p->created_at;
                }

                return [
                    'id' => $p->id,
                    'date' => $date?->format('Y-m-d H:i'),
                    'drug_name' => $p->drug?->name ?? $p->medication_name,
                    'generic_name' => $p->drug?->generic_name,
                    'form' => $p->drug?->form ?? $p->dosage_form,
                    'strength' => $p->drug?->strength,
                    'dose_quantity' => $p->dose_quantity,
                    'frequency' => $p->frequency,
                    'duration' => $p->duration,
                    'quantity' => $p->quantity,
                    'instructions' => $p->instructions,
                    'status' => $p->status,
                ];
            });

        // Get all lab results
        $allLabResults = \App\Models\LabOrder::with([
            'labService:id,name,code,is_imaging,test_parameters',
            'orderedBy:id,name',
        ])
            ->where(function ($query) use ($patient) {
                $query->whereHasMorph('orderable', [\App\Models\Consultation::class], function ($q) use ($patient) {
                    $q->whereHas('patientCheckin', fn ($sq) => $sq->where('patient_id', $patient->id));
                })
                    ->orWhereHasMorph('orderable', [\App\Models\WardRound::class], function ($q) use ($patient) {
                        $q->whereHas('patientAdmission', fn ($sq) => $sq->where('patient_id', $patient->id));
                    });
            })
            ->where('status', 'completed')
            ->orderByDesc('result_entered_at')
            ->limit(100)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'service_name' => $l->labService?->name,
                'code' => $l->labService?->code,
                'is_imaging' => $l->labService?->is_imaging,
                'test_parameters' => $l->labService?->test_parameters,
                'ordered_by' => $l->orderedBy?->name,
                'ordered_at' => $l->ordered_at?->format('Y-m-d H:i'),
                'result_entered_at' => $l->result_entered_at?->format('Y-m-d H:i'),
                'result_values' => $l->result_values,
                'result_notes' => $l->result_notes,
            ]);

        return [
            'consultations' => $consultations,
            'vitals' => $vitals,
            'admissions' => $admissions,
            'prescriptions' => $allPrescriptions,
            'lab_results' => $allLabResults,
            'theatre_procedures' => $this->getTheatreProcedures($patient),
            'minor_procedures' => $this->getMinorProceduresHistory($patient),
        ];
    }

    /**
     * Get theatre/surgical procedures for a patient
     */
    private function getTheatreProcedures(Patient $patient): \Illuminate\Support\Collection
    {
        return \App\Models\ConsultationProcedure::with([
            'procedureType:id,name,code,category',
            'doctor:id,name',
            'consultation.patientCheckin.department:id,name',
        ])
            ->whereHas('consultation.patientCheckin', fn ($q) => $q->where('patient_id', $patient->id))
            ->orderByDesc('performed_at')
            ->limit(50)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'performed_at' => $p->performed_at?->format('Y-m-d H:i'),
                'procedure_name' => $p->procedureType?->name,
                'procedure_code' => $p->procedureType?->code,
                'category' => $p->procedureType?->category,
                'doctor' => $p->doctor?->name,
                'department' => $p->consultation?->patientCheckin?->department?->name,
                'indication' => $p->indication,
                'assistant' => $p->assistant,
                'anaesthetist' => $p->anaesthetist,
                'anaesthesia_type' => $p->anaesthesia_type,
                'procedure_subtype' => $p->procedure_subtype,
                'procedure_steps' => $p->procedure_steps,
                'findings' => $p->findings,
                'plan' => $p->plan,
                'comments' => $p->comments,
                'estimated_gestational_age' => $p->estimated_gestational_age,
                'parity' => $p->parity,
            ]);
    }

    private function getMinorProceduresHistory(Patient $patient): \Illuminate\Support\Collection
    {
        return \App\Models\MinorProcedure::with([
            'patientCheckin:id,department_id,checked_in_at',
            'patientCheckin.department:id,name',
            'patientCheckin.vitalSigns' => fn ($q) => $q->with('recordedBy:id,name')->orderByDesc('recorded_at'),
            'nurse:id,name',
            'procedureType:id,name,code',
            'diagnoses:id,code,diagnosis',
            'supplies.drug:id,name,generic_name,form,strength',
        ])
            ->whereHas('patientCheckin', fn ($q) => $q->where('patient_id', $patient->id))
            ->where('status', 'completed')
            ->orderByDesc('performed_at')
            ->limit(50)
            ->get()
            ->map(function ($mp) {
                $vitals = $mp->patientCheckin?->vitalSigns?->first();

                return [
                    'id' => $mp->id,
                    'date' => $mp->patientCheckin?->checked_in_at?->format('Y-m-d H:i'),
                    'nurse' => $mp->nurse?->name,
                    'department' => $mp->patientCheckin?->department?->name,
                    'procedure_name' => $mp->procedureType?->name,
                    'procedure_code' => $mp->procedureType?->code,
                    'procedure_notes' => $mp->procedure_notes,
                    'vitals' => $vitals ? [
                        'blood_pressure' => $vitals->blood_pressure,
                        'temperature' => $vitals->temperature,
                        'pulse_rate' => $vitals->pulse_rate,
                        'respiratory_rate' => $vitals->respiratory_rate,
                        'oxygen_saturation' => $vitals->oxygen_saturation,
                        'weight' => $vitals->weight,
                        'height' => $vitals->height,
                        'bmi' => $vitals->bmi,
                    ] : null,
                    'diagnoses' => $mp->diagnoses->map(fn ($d) => [
                        'code' => $d->code,
                        'description' => $d->diagnosis,
                    ]),
                    'supplies' => $mp->supplies->map(fn ($s) => [
                        'drug_name' => $s->drug?->name,
                        'quantity' => $s->quantity_dispensed ?? $s->quantity_requested,
                        'status' => $s->status,
                    ]),
                ];
            });
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

            // Find ALL patient numbers with this year suffix and get the highest number
            $patientsWithPattern = Patient::where('patient_number', 'like', "%{$suffixPattern}")
                ->pluck('patient_number');

            $highestNumber = 0;
            foreach ($patientsWithPattern as $patientNumber) {
                // Extract the numeric part from the beginning (before the separator)
                $numericPart = preg_replace('/[^0-9].*/', '', $patientNumber);
                $number = (int) $numericPart;
                if ($number > $highestNumber) {
                    $highestNumber = $number;
                }
            }

            $newNumber = $highestNumber + 1;
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

        // Find ALL patient numbers with this pattern and get the highest number
        $patientsWithPattern = Patient::where('patient_number', 'like', "{$basePattern}%")
            ->pluck('patient_number');

        $highestNumber = 0;
        foreach ($patientsWithPattern as $patientNumber) {
            // Extract the numeric part from the end
            $numericPart = substr($patientNumber, -$padding);
            $number = (int) $numericPart;
            if ($number > $highestNumber) {
                $highestNumber = $number;
            }
        }

        $newNumber = $highestNumber + 1;

        return $basePattern.str_pad($newNumber, $padding, '0', STR_PAD_LEFT);
    }

    /**
     * Get active check-in without insurance for a patient.
     * Only returns check-ins that are eligible for insurance application.
     */
    private function getActiveCheckinWithoutInsurance(Patient $patient): ?array
    {
        $today = now()->toDateString();

        $checkin = \App\Models\PatientCheckin::where('patient_id', $patient->id)
            ->whereNull('claim_check_code')
            ->where(function ($query) use ($today) {
                // OPD statuses - always eligible
                $query->whereIn('status', ['checked_in', 'vitals_taken', 'awaiting_consultation', 'in_consultation'])
                    // Admitted patients - only if admitted today
                    ->orWhere(function ($q) use ($today) {
                        $q->where('status', 'admitted')
                            ->whereDate('checked_in_at', $today);
                    });
            })
            ->with('department')
            ->first();

        if (! $checkin) {
            return null;
        }

        return [
            'id' => $checkin->id,
            'checked_in_at' => $checkin->checked_in_at->format('Y-m-d H:i'),
            'department' => [
                'id' => $checkin->department->id,
                'name' => $checkin->department->name,
            ],
            'status' => $checkin->status,
            'is_admitted' => $checkin->status === 'admitted',
        ];
    }

    /**
     * Get NHIS settings for the frontend.
     */
    private function getNhisSettings(): array
    {
        $nhisSettings = \App\Models\NhisSettings::getInstance();
        $nhisCredentials = null;

        if ($nhisSettings->verification_mode === 'extension' && $nhisSettings->nhia_username) {
            try {
                $nhisCredentials = [
                    'username' => $nhisSettings->nhia_username,
                    'password' => $nhisSettings->nhia_password,
                ];
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                // Password was encrypted with different APP_KEY, ignore
            }
        }

        return [
            'verification_mode' => $nhisSettings->verification_mode,
            'nhia_portal_url' => $nhisSettings->nhia_portal_url,
            'auto_open_portal' => $nhisSettings->auto_open_portal,
            'credentials' => $nhisCredentials,
        ];
    }
}
