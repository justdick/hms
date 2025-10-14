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

        return Inertia::render('Billing/Payments/Index', [
            'stats' => $stats,
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
                $totalCharges = 0;
                $visits = [];

                foreach ($patient->checkins as $checkin) {
                    $visitCharges = $checkin->charges->where('status', 'pending');
                    if ($visitCharges->count() > 0) {
                        $visitTotal = $visitCharges->sum('amount');
                        $totalPending += $visitTotal;
                        $totalCharges += $visitCharges->count();

                        $visits[] = [
                            'checkin_id' => $checkin->id,
                            'department' => $checkin->department,
                            'checked_in_at' => $checkin->created_at->format('M j, Y'),
                            'total_pending' => $visitTotal,
                            'charges_count' => $visitCharges->count(),
                            'charges' => $visitCharges->map(function ($charge) {
                                return [
                                    'id' => $charge->id,
                                    'description' => $charge->description,
                                    'amount' => $charge->amount,
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
            'department:id,name',
        ]);

        $charges = $this->billingService->getPendingCharges($checkin);
        $totalAmount = $charges->sum('amount');
        $paidCharges = Charge::forPatient($checkin->id)->paid()->get();

        return Inertia::render('Billing/Payments/Show', [
            'checkin' => $checkin,
            'charges' => $charges,
            'paidCharges' => $paidCharges,
            'totalAmount' => $totalAmount,
            'canProceedWithServices' => $this->getServiceStatus($checkin),
        ]);
    }

    /**
     * Process payment for charges
     */
    public function processPayment(Request $request, PatientCheckin $checkin)
    {
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

        $totalAmount = $charges->sum('amount');
        $amountPaid = $validated['amount_paid'];

        if ($amountPaid > $totalAmount) {
            return back()->withErrors([
                'amount_paid' => 'Payment amount cannot exceed total charges',
            ]);
        }

        // Process payment proportionally across charges
        $remainingPayment = $amountPaid;

        foreach ($charges as $charge) {
            if ($remainingPayment <= 0) {
                break;
            }

            $chargeAmount = $charge->amount;
            $paymentForCharge = min($remainingPayment, $chargeAmount);

            $charge->update([
                'paid_amount' => $paymentForCharge,
                'paid_at' => now(),
                'status' => $paymentForCharge >= $chargeAmount ? 'paid' : 'partial',
                'is_emergency_override' => $validated['emergency_override'] ?? false,
            ]);

            $remainingPayment -= $paymentForCharge;
        }

        // Log payment
        $this->logPayment($checkin, $validated, $amountPaid);

        $message = $amountPaid >= $totalAmount
            ? 'Payment processed successfully. All charges cleared.'
            : 'Partial payment processed successfully.';

        return back()->with('success', $message);
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

        $serviceStatus = $this->getServiceStatus($checkin);

        return response()->json([
            'has_pending_charges' => $pendingCharges->isNotEmpty(),
            'total_pending' => $totalPending,
            'pending_charges' => $pendingCharges,
            'service_status' => $serviceStatus,
            'emergency_overrides' => $this->getActiveOverrides($checkin),
        ]);
    }

    /**
     * Quick payment for a single charge
     */
    public function quickPay(Request $request, Charge $charge)
    {
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
