<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\PatientCheckin;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(
        private BillingService $billingService
    ) {}

    /**
     * Show billing dashboard with search interface
     */
    public function index(Request $request): Response
    {
        $stats = [
            'pending_charges' => Charge::pending()->count(),
            'pending_amount' => Charge::pending()->sum('amount'),
            'paid_today' => Charge::paid()->whereDate('paid_at', today())->sum('amount'),
            'total_outstanding' => Charge::pending()->sum('amount'),
        ];

        $permissions = [
            'canProcessPayment' => auth()->user()->can('billing.create'),
            'canWaiveCharges' => auth()->user()->can('billing.waive-charges'),
            'canAdjustCharges' => auth()->user()->can('billing.adjust-charges'),
            'canOverrideServices' => auth()->user()->can('billing.emergency-override'),
            'canCancelCharges' => auth()->user()->can('billing.cancel-charges'),
        ];

        return Inertia::render('Billing/Payments/Index', [
            'stats' => $stats,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Search patients with pending charges for billing (patient-centric approach)
     */
    public function searchPatients(Request $request)
    {
        $search = $request->query('search');

        if (! $search || strlen($search) < 2) {
            return response()->json(['patients' => []]);
        }

        // Find patients with pending charges across all their visits
        $patients = \App\Models\Patient::with([
            'checkins.charges' => function ($q) {
                $q->where('status', 'pending');
            },
            'checkins.department:id,name',
        ])
            ->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('phone_number', 'LIKE', "%{$search}%")
                    ->orWhere('patient_number', 'LIKE', "%{$search}%");
            })
            ->whereHas('checkins.charges', function ($q) {
                $q->where('status', 'pending');
            })
            ->limit(10)
            ->get()
            ->map(function ($patient) {
                // Calculate total pending across all visits
                $totalPending = 0;
                $totalPatientOwes = 0; // Total copay amount patient needs to pay
                $totalInsuranceCovered = 0;
                $totalCharges = 0;
                $visits = [];

                foreach ($patient->checkins as $checkin) {
                    $visitCharges = $checkin->charges->where('status', 'pending');
                    if ($visitCharges->count() > 0) {
                        $visitTotal = $visitCharges->sum('amount');
                        $visitCopay = $visitCharges->sum('patient_copay_amount');
                        $visitInsurance = $visitCharges->sum('insurance_covered_amount');

                        $totalPending += $visitTotal;
                        $totalPatientOwes += $visitCopay;
                        $totalInsuranceCovered += $visitInsurance;
                        $totalCharges += $visitCharges->count();

                        $visits[] = [
                            'checkin_id' => $checkin->id,
                            'department' => $checkin->department,
                            'checked_in_at' => $checkin->created_at->format('M j, Y'),
                            'total_pending' => $visitTotal,
                            'patient_copay' => $visitCopay,
                            'insurance_covered' => $visitInsurance,
                            'charges_count' => $visitCharges->count(),
                            'charges' => $visitCharges->map(function ($charge) {
                                return [
                                    'id' => $charge->id,
                                    'description' => $charge->description,
                                    'amount' => $charge->amount,
                                    'is_insurance_claim' => $charge->is_insurance_claim,
                                    'insurance_covered_amount' => $charge->insurance_covered_amount,
                                    'patient_copay_amount' => $charge->patient_copay_amount,
                                    'service_type' => $charge->service_type,
                                    'charged_at' => $charge->charged_at->format('M j, Y'),
                                ];
                            }),
                        ];
                    }
                }

                // Sort visits by most recent first
                usort($visits, function ($a, $b) {
                    return strtotime($b['checked_in_at']) - strtotime($a['checked_in_at']);
                });

                return [
                    'patient_id' => $patient->id,
                    'patient' => [
                        'id' => $patient->id,
                        'first_name' => $patient->first_name,
                        'last_name' => $patient->last_name,
                        'patient_number' => $patient->patient_number,
                        'phone_number' => $patient->phone_number,
                    ],
                    'total_pending' => $totalPending,
                    'total_patient_owes' => $totalPatientOwes,
                    'total_insurance_covered' => $totalInsuranceCovered,
                    'total_charges' => $totalCharges,
                    'visits_with_charges' => count($visits),
                    'visits' => $visits,
                ];
            });

        return response()->json(['patients' => $patients]);
    }

    /**
     * Show patient billing summary and payment form
     */
    public function show(PatientCheckin $checkin): Response
    {
        $checkin->load([
            'patient:id,first_name,last_name,phone_number,date_of_birth',
            'patient.activeInsurance.plan.provider',
            'department:id,name',
        ]);

        $charges = $this->billingService->getPendingCharges($checkin);
        $totalAmount = $charges->sum('amount');
        $totalPatientOwes = $charges->sum('patient_copay_amount');
        $totalInsuranceCovered = $charges->sum('insurance_covered_amount');

        $paidCharges = Charge::forPatient($checkin->id)->paid()->get();

        // Get active overrides
        $activeOverrides = \App\Models\ServiceAccessOverride::where('patient_checkin_id', $checkin->id)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->with('authorizedBy:id,name')
            ->get()
            ->map(function ($override) {
                return [
                    'id' => $override->id,
                    'service_type' => $override->service_type,
                    'service_code' => $override->service_code,
                    'reason' => $override->reason,
                    'authorized_by' => [
                        'id' => $override->authorizedBy->id,
                        'name' => $override->authorizedBy->name,
                    ],
                    'authorized_at' => $override->authorized_at->format('M j, Y g:i A'),
                    'expires_at' => $override->expires_at->format('M j, Y g:i A'),
                    'remaining_duration' => $override->getRemainingDuration(),
                ];
            });

        return Inertia::render('Billing/Payments/Show', [
            'checkin' => $checkin,
            'charges' => $charges,
            'paidCharges' => $paidCharges,
            'totalAmount' => $totalAmount,
            'totalPatientOwes' => $totalPatientOwes,
            'totalInsuranceCovered' => $totalInsuranceCovered,
            'patientInsurance' => $checkin->patient->activeInsurance ? [
                'plan_name' => $checkin->patient->activeInsurance->plan->name ?? null,
                'provider_name' => $checkin->patient->activeInsurance->plan->provider->name ?? null,
                'policy_number' => $checkin->patient->activeInsurance->policy_number,
            ] : null,
            'canProceedWithServices' => $this->getServiceStatus($checkin),
            'activeOverrides' => $activeOverrides,
        ]);
    }

    /**
     * Process payment for charges (enhanced for inline form submission)
     */
    public function processPayment(Request $request, PatientCheckin $checkin)
    {
        $this->authorize('create', Charge::class);

        $validated = $request->validate([
            'charges' => 'required|array|min:1',
            'charges.*' => 'exists:charges,id',
            'payment_method' => 'required|in:cash,card,mobile_money,insurance,bank_transfer',
            'amount_paid' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
            'emergency_override' => 'boolean',
        ]);

        $charges = Charge::whereIn('id', $validated['charges'])
            ->where('patient_checkin_id', $checkin->id)
            ->where('status', 'pending')
            ->get();

        if ($charges->isEmpty()) {
            return back()->withErrors([
                'charges' => 'No valid pending charges found',
            ]);
        }

        // For insurance claims, patient only needs to pay copay amount
        // For non-insurance charges, patient pays full amount
        $totalPatientOwes = $charges->sum(function ($charge) {
            return $charge->is_insurance_claim
                ? $charge->patient_copay_amount
                : $charge->amount;
        });

        $totalAmount = $charges->sum('amount');
        $amountPaid = $validated['amount_paid'];

        if ($amountPaid > $totalPatientOwes) {
            return back()->withErrors([
                'amount_paid' => 'Payment amount cannot exceed patient copay amount',
            ]);
        }

        try {
            \DB::transaction(function () use ($charges, $validated, $amountPaid, $checkin) {
                // Process payment proportionally across charges
                $remainingPayment = $amountPaid;

                foreach ($charges as $charge) {
                    if ($remainingPayment <= 0) {
                        break;
                    }

                    // Determine amount due from patient for this charge
                    $amountDue = $charge->is_insurance_claim
                        ? $charge->patient_copay_amount
                        : $charge->amount;

                    $paymentForCharge = min($remainingPayment, $amountDue);

                    $charge->update([
                        'paid_amount' => $paymentForCharge,
                        'paid_at' => now(),
                        'status' => $paymentForCharge >= $amountDue ? 'paid' : 'partial',
                        'is_emergency_override' => $validated['emergency_override'] ?? false,
                    ]);

                    $remainingPayment -= $paymentForCharge;
                }

                // Log payment
                $this->logPayment($checkin, $validated, $amountPaid);
            });

            $message = $amountPaid >= $totalPatientOwes
                ? 'Payment processed successfully. All patient copays cleared.'
                : 'Partial payment processed successfully.';

            return back()->with('success', $message);
        } catch (\Exception $e) {
            \Log::error('Payment processing failed', [
                'patient_checkin_id' => $checkin->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Payment processing failed. Please try again.',
            ]);
        }
    }

    /**
     * Set emergency override for a service
     */
    public function emergencyOverride(Request $request, PatientCheckin $checkin)
    {
        $validated = $request->validate([
            'service_type' => 'required|string',
            'service_code' => 'nullable|string',
            'reason' => 'required|string|max:500',
        ]);

        // Set emergency override in session for this patient
        session([
            "emergency_override_{$checkin->id}_{$validated['service_type']}" => [
                'service_type' => $validated['service_type'],
                'service_code' => $validated['service_code'],
                'reason' => $validated['reason'],
                'authorized_by' => auth()->id(),
                'authorized_at' => now(),
                'expires_at' => now()->addHours(2), // Override expires in 2 hours
            ],
        ]);

        return back()->with('success', 'Emergency override activated for this service');
    }

    /**
     * Get billing status for a patient
     */
    public function getBillingStatus(PatientCheckin $checkin)
    {
        $pendingCharges = $this->billingService->getPendingCharges($checkin);
        $totalPending = $pendingCharges->sum('amount');
        $totalPatientOwes = $pendingCharges->sum('patient_copay_amount');

        $serviceStatus = $this->getServiceStatus($checkin);

        // Get active overrides from database
        $activeOverrides = \App\Models\ServiceAccessOverride::where('patient_checkin_id', $checkin->id)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->with('authorizedBy:id,name')
            ->get()
            ->map(function ($override) {
                return [
                    'id' => $override->id,
                    'service_type' => $override->service_type,
                    'service_code' => $override->service_code,
                    'reason' => $override->reason,
                    'authorized_by' => [
                        'id' => $override->authorizedBy->id,
                        'name' => $override->authorizedBy->name,
                    ],
                    'authorized_at' => $override->authorized_at->format('M j, Y g:i A'),
                    'expires_at' => $override->expires_at->format('M j, Y g:i A'),
                    'remaining_duration' => $override->getRemainingDuration(),
                ];
            });

        return response()->json([
            'has_pending_charges' => $pendingCharges->isNotEmpty(),
            'total_pending' => $totalPending,
            'total_patient_owes' => $totalPatientOwes,
            'pending_charges' => $pendingCharges,
            'service_status' => $serviceStatus,
            'active_overrides' => $activeOverrides,
        ]);
    }

    /**
     * Quick payment for a single charge
     */
    public function quickPay(Request $request, Charge $charge)
    {
        $this->authorize('create', Charge::class);

        if ($charge->status !== 'pending') {
            return back()->withErrors([
                'error' => 'This charge has already been paid or cancelled',
            ]);
        }

        $validated = $request->validate([
            'payment_method' => 'required|in:cash,card,mobile_money,insurance,bank_transfer',
            'amount' => 'nullable|numeric|min:0|max:'.$charge->amount,
            'notes' => 'nullable|string|max:500',
        ]);

        $amount = $validated['amount'] ?? $charge->amount;

        $charge->update([
            'paid_amount' => $amount,
            'paid_at' => now(),
            'status' => $amount >= $charge->amount ? 'paid' : 'partial',
        ]);

        $this->logPayment($charge->patientCheckin, $validated, $amount);

        return back()->with('success', 'Payment processed successfully');
    }

    /**
     * Quick pay all charges for a patient
     */
    public function quickPayAll(Request $request)
    {
        $this->authorize('create', Charge::class);

        $validated = $request->validate([
            'patient_checkin_id' => 'required|exists:patient_checkins,id',
            'payment_method' => 'required|in:cash,card,mobile_money,insurance,bank_transfer',
            'charges' => 'required|array|min:1',
            'charges.*' => 'exists:charges,id',
        ]);

        $checkin = PatientCheckin::findOrFail($validated['patient_checkin_id']);

        $charges = Charge::whereIn('id', $validated['charges'])
            ->where('patient_checkin_id', $checkin->id)
            ->where('status', 'pending')
            ->get();

        if ($charges->isEmpty()) {
            return back()->withErrors([
                'charges' => 'No valid pending charges found',
            ]);
        }

        try {
            \DB::transaction(function () use ($charges, $validated, $checkin) {
                $totalPaid = 0;

                foreach ($charges as $charge) {
                    // For insurance claims, patient only pays copay
                    $amountDue = $charge->is_insurance_claim
                        ? $charge->patient_copay_amount
                        : $charge->amount;

                    $charge->update([
                        'paid_amount' => $amountDue,
                        'paid_at' => now(),
                        'status' => 'paid',
                    ]);

                    $totalPaid += $amountDue;
                }

                $this->logPayment($checkin, $validated, $totalPaid);
            });

            return back()->with('success', 'All charges paid successfully');
        } catch (\Exception $e) {
            \Log::error('Quick pay all failed', [
                'patient_checkin_id' => $checkin->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Payment processing failed. Please try again.',
            ]);
        }
    }

    private function getServiceStatus(PatientCheckin $checkin): array
    {
        return [
            'consultation' => $this->billingService->canProceedWithService($checkin, 'consultation'),
            'laboratory' => $this->billingService->canProceedWithService($checkin, 'laboratory'),
            'pharmacy' => $this->billingService->canProceedWithService($checkin, 'pharmacy'),
            'ward' => $this->billingService->canProceedWithService($checkin, 'ward', 'bed_assignment'),
        ];
    }

    private function getActiveOverrides(PatientCheckin $checkin): array
    {
        $overrides = [];
        $sessionKey = "emergency_override_{$checkin->id}";

        foreach (session()->all() as $key => $value) {
            if (str_starts_with($key, $sessionKey) &&
                isset($value['expires_at']) &&
                now()->lt($value['expires_at'])) {
                $overrides[] = $value;
            }
        }

        return $overrides;
    }

    private function logPayment(PatientCheckin $checkin, array $validated, float $amount): void
    {
        logger('Payment processed', [
            'patient_checkin_id' => $checkin->id,
            'patient_name' => $checkin->patient->first_name.' '.$checkin->patient->last_name,
            'amount' => $amount,
            'method' => $validated['payment_method'],
            'processed_by' => auth()->user()->name ?? 'System',
            'notes' => $validated['notes'] ?? null,
            'timestamp' => now(),
        ]);
    }
}
