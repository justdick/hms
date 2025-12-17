<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\SetCreditLimitRequest;
use App\Http\Requests\Billing\StoreAccountDepositRequest;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\PaymentMethod;
use App\Services\PatientAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PatientAccountController extends Controller
{
    public function __construct(private PatientAccountService $accountService) {}

    /**
     * Show patient accounts management page.
     */
    public function index(Request $request): Response
    {
        $accounts = PatientAccount::with(['patient', 'creditAuthorizedBy'])
            ->when($request->query('search'), function ($q, $search) {
                $q->whereHas('patient', fn ($pq) => $pq->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('patient_number', 'like', "%{$search}%"));
            })
            ->when($request->query('filter') === 'credit', fn ($q) => $q->where('credit_limit', '>', 0))
            ->when($request->query('filter') === 'owing', fn ($q) => $q->where('balance', '<', 0))
            ->when($request->query('filter') === 'prepaid', fn ($q) => $q->where('balance', '>', 0))
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Billing/PatientAccounts/Index', [
            'accounts' => $accounts,
            'filters' => $request->only(['search', 'filter']),
            'paymentMethods' => PaymentMethod::where('is_active', true)->get(),
        ]);
    }

    /**
     * Store a new deposit.
     */
    public function deposit(StoreAccountDepositRequest $request)
    {
        $patient = Patient::findOrFail($request->validated('patient_id'));

        $transaction = $this->accountService->deposit(
            patient: $patient,
            amount: $request->validated('amount'),
            paymentMethodId: $request->validated('payment_method_id'),
            processedBy: $request->user(),
            paymentReference: $request->validated('payment_reference'),
            notes: $request->validated('notes'),
        );

        return redirect()->back()->with('success', sprintf(
            'Deposit of GHS %.2f recorded for %s. Transaction #%s',
            $transaction->amount,
            $patient->full_name,
            $transaction->transaction_number
        ));
    }

    /**
     * Set credit limit for a patient.
     */
    public function setCreditLimit(SetCreditLimitRequest $request, Patient $patient)
    {
        $account = $this->accountService->setCreditLimit(
            patient: $patient,
            creditLimit: $request->validated('credit_limit'),
            authorizedBy: $request->user(),
            reason: $request->validated('reason'),
        );

        $message = $account->credit_limit > 0
            ? sprintf('Credit limit of GHS %.2f set for %s', $account->credit_limit, $patient->full_name)
            : sprintf('Credit limit removed for %s', $patient->full_name);

        return redirect()->back()->with('success', $message);
    }

    /**
     * Get patient account summary (AJAX).
     */
    public function summary(Patient $patient): JsonResponse
    {
        $summary = $this->accountService->getAccountSummary($patient);
        $summary['patient'] = [
            'id' => $patient->id,
            'full_name' => $patient->full_name,
            'patient_number' => $patient->patient_number,
        ];

        return response()->json($summary);
    }

    /**
     * Get patient transaction history (AJAX).
     */
    public function transactions(Patient $patient): JsonResponse
    {
        $transactions = $this->accountService->getTransactionHistory($patient);

        return response()->json(['transactions' => $transactions]);
    }

    /**
     * Show patient account details.
     */
    public function show(Patient $patient): Response
    {
        $account = PatientAccount::where('patient_id', $patient->id)
            ->with('creditAuthorizedBy')
            ->first();

        $transactions = $account
            ? $account->transactions()
                ->with(['paymentMethod', 'processedBy', 'charge'])
                ->orderByDesc('transacted_at')
                ->paginate(20)
            : null;

        return Inertia::render('Billing/PatientAccounts/Show', [
            'patient' => $patient->only(['id', 'first_name', 'last_name', 'patient_number', 'phone_number']),
            'account' => $account,
            'transactions' => $transactions,
            'paymentMethods' => PaymentMethod::where('is_active', true)->get(),
        ]);
    }

    /**
     * Search patients for account operations (AJAX).
     */
    public function searchPatients(Request $request): JsonResponse
    {
        $search = $request->query('search');

        if (! $search || strlen($search) < 2) {
            return response()->json(['patients' => []]);
        }

        $patients = Patient::with('account')
            ->where(function ($q) use ($search) {
                // Check if search contains multiple words (likely first + last name)
                $words = preg_split('/\s+/', trim($search));

                if (count($words) >= 2) {
                    // Multi-word search: match first word against first_name AND second against last_name
                    $q->where(function ($subQ) use ($words) {
                        $subQ->where('first_name', 'like', "%{$words[0]}%")
                            ->where('last_name', 'like', "%{$words[1]}%");
                    })->orWhere(function ($subQ) use ($words) {
                        // Also try reverse order (last_name first_name)
                        $subQ->where('last_name', 'like', "%{$words[0]}%")
                            ->where('first_name', 'like', "%{$words[1]}%");
                    });
                } else {
                    // Single word: search across all fields
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('patient_number', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%");
                }
            })
            ->limit(10)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'full_name' => $p->full_name,
                'patient_number' => $p->patient_number,
                'phone_number' => $p->phone_number,
                'account_balance' => $p->account?->balance ?? 0,
                'credit_limit' => $p->account?->credit_limit ?? 0,
                'available_balance' => $p->account?->available_balance ?? 0,
            ]);

        return response()->json(['patients' => $patients]);
    }

    /**
     * Make an adjustment to patient account.
     */
    public function adjustment(Request $request, Patient $patient)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $transaction = $this->accountService->makeAdjustment(
                patient: $patient,
                amount: $validated['amount'],
                processedBy: $request->user(),
                reason: $validated['reason'] ?? null,
            );

            $type = $transaction->amount >= 0 ? 'Credit' : 'Debit';

            return redirect()->back()->with('success', sprintf(
                '%s adjustment of GHS %.2f processed. Transaction #%s',
                $type,
                abs($transaction->amount),
                $transaction->transaction_number
            ));
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors([
                'amount' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process refund from patient account.
     */
    public function refund(Request $request, Patient $patient)
    {
        $this->authorize('refund', PatientAccount::class);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $account = $patient->account;

        if (! $account || $account->balance < $validated['amount']) {
            return redirect()->back()->withErrors([
                'amount' => 'Insufficient balance for refund',
            ]);
        }

        $transaction = $this->accountService->processRefund(
            patient: $patient,
            amount: $validated['amount'],
            processedBy: $request->user(),
            reason: $validated['reason'],
        );

        return redirect()->back()->with('success', sprintf(
            'Refund of GHS %.2f processed. Transaction #%s',
            abs($transaction->amount),
            $transaction->transaction_number
        ));
    }
}
