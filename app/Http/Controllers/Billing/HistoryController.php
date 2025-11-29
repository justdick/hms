<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\PaymentAuditLog;
use App\Models\PaymentMethod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    /**
     * Display the payment history page with filters.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAll', Charge::class);

        // Parse filters
        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))
            : today()->subDays(30);
        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))
            : today();
        $cashierId = $request->query('cashier_id');
        $patientSearch = $request->query('patient_search');
        $paymentMethod = $request->query('payment_method');
        $minAmount = $request->query('min_amount');
        $maxAmount = $request->query('max_amount');
        $receiptSearch = $request->query('receipt_search');

        // Build query for paid charges
        $query = Charge::with([
            'patientCheckin.patient',
            'patientCheckin.department',
            'processedByUser',
        ])
            ->whereIn('status', ['paid', 'partial', 'voided', 'refunded'])
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$startDate->startOfDay(), $endDate->copy()->endOfDay()])
            ->orderByDesc('paid_at');

        // Apply cashier filter
        if ($cashierId) {
            $query->where('processed_by', $cashierId);
        }

        // Apply patient search (name or patient number)
        if ($patientSearch) {
            $query->whereHas('patientCheckin.patient', function ($q) use ($patientSearch) {
                $q->where('patient_number', 'like', "%{$patientSearch}%")
                    ->orWhere('first_name', 'like', "%{$patientSearch}%")
                    ->orWhere('last_name', 'like', "%{$patientSearch}%");
            });
        }

        // Apply payment method filter
        if ($paymentMethod) {
            $query->whereJsonContains('metadata->payment_method', $paymentMethod);
        }

        // Apply amount range filters
        if ($minAmount) {
            $query->where('paid_amount', '>=', $minAmount);
        }
        if ($maxAmount) {
            $query->where('paid_amount', '<=', $maxAmount);
        }

        // Apply receipt number search
        if ($receiptSearch) {
            $query->where('receipt_number', 'like', "%{$receiptSearch}%");
        }

        // Paginate results
        $payments = $query->paginate(25)->withQueryString();

        // Get cashiers for filter dropdown
        $cashiers = User::whereHas('charges', function ($q) {
            $q->whereIn('status', ['paid', 'partial']);
        })
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // Get payment methods for filter
        $paymentMethods = PaymentMethod::active()->get(['id', 'name', 'code']);

        // Calculate summary stats for the filtered results
        $summaryQuery = Charge::whereIn('status', ['paid', 'partial'])
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$startDate->startOfDay(), $endDate->copy()->endOfDay()]);

        if ($cashierId) {
            $summaryQuery->where('processed_by', $cashierId);
        }
        if ($patientSearch) {
            $summaryQuery->whereHas('patientCheckin.patient', function ($q) use ($patientSearch) {
                $q->where('patient_number', 'like', "%{$patientSearch}%")
                    ->orWhere('first_name', 'like', "%{$patientSearch}%")
                    ->orWhere('last_name', 'like', "%{$patientSearch}%");
            });
        }
        if ($paymentMethod) {
            $summaryQuery->whereJsonContains('metadata->payment_method', $paymentMethod);
        }
        if ($minAmount) {
            $summaryQuery->where('paid_amount', '>=', $minAmount);
        }
        if ($maxAmount) {
            $summaryQuery->where('paid_amount', '<=', $maxAmount);
        }
        if ($receiptSearch) {
            $summaryQuery->where('receipt_number', 'like', "%{$receiptSearch}%");
        }

        $summary = [
            'total_amount' => (float) $summaryQuery->sum('paid_amount'),
            'transaction_count' => $summaryQuery->count(),
        ];

        return Inertia::render('Billing/History/Index', [
            'payments' => $payments,
            'cashiers' => $cashiers,
            'paymentMethods' => $paymentMethods,
            'summary' => $summary,
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'cashier_id' => $cashierId,
                'patient_search' => $patientSearch,
                'payment_method' => $paymentMethod,
                'min_amount' => $minAmount,
                'max_amount' => $maxAmount,
                'receipt_search' => $receiptSearch,
            ],
            'permissions' => [
                'canVoid' => auth()->user()->can('billing.void'),
                'canRefund' => auth()->user()->can('billing.refund'),
            ],
        ]);
    }

    /**
     * Get payment detail with audit trail.
     */
    public function show(Request $request, Charge $charge): Response
    {
        $this->authorize('viewAll', Charge::class);

        // Load relationships
        $charge->load([
            'patientCheckin.patient',
            'patientCheckin.department',
            'processedByUser',
            'prescription.drug',
        ]);

        // Get audit trail for this charge
        $auditTrail = PaymentAuditLog::where('charge_id', $charge->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user_name' => $log->user?->name ?? 'System',
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'reason' => $log->reason,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at->toIso8601String(),
                ];
            });

        return Inertia::render('Billing/History/Show', [
            'charge' => [
                'id' => $charge->id,
                'service_type' => $charge->service_type,
                'service_code' => $charge->service_code,
                'description' => $charge->description,
                'amount' => (float) $charge->amount,
                'paid_amount' => (float) $charge->paid_amount,
                'status' => $charge->status,
                'receipt_number' => $charge->receipt_number,
                'paid_at' => $charge->paid_at?->toIso8601String(),
                'charged_at' => $charge->charged_at?->toIso8601String(),
                'metadata' => $charge->metadata,
                'notes' => $charge->notes,
                'patient' => $charge->patientCheckin?->patient ? [
                    'id' => $charge->patientCheckin->patient->id,
                    'patient_number' => $charge->patientCheckin->patient->patient_number,
                    'name' => $charge->patientCheckin->patient->first_name.' '.$charge->patientCheckin->patient->last_name,
                ] : null,
                'department' => $charge->patientCheckin?->department ? [
                    'id' => $charge->patientCheckin->department->id,
                    'name' => $charge->patientCheckin->department->name,
                ] : null,
                'processed_by' => $charge->processedByUser ? [
                    'id' => $charge->processedByUser->id,
                    'name' => $charge->processedByUser->name,
                ] : null,
            ],
            'auditTrail' => $auditTrail,
        ]);
    }
}
