<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\Reconciliation;
use App\Models\User;
use App\Services\ReconciliationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationController extends Controller
{
    public function __construct(
        private ReconciliationService $reconciliationService
    ) {}

    /**
     * Display a listing of reconciliations.
     */
    public function index(Request $request): Response
    {
        $this->authorize('reconcile', Charge::class);

        // Parse filters
        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))
            : today()->subDays(30);
        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))
            : today();
        $cashierId = $request->query('cashier_id');
        $status = $request->query('status');

        // Get reconciliation history
        $reconciliations = $this->reconciliationService->getReconciliationHistory([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'cashier_id' => $cashierId,
            'status' => $status,
        ]);

        // Get summary statistics
        $summary = $this->reconciliationService->getReconciliationSummary($startDate, $endDate);

        // Get cashiers for filter dropdown
        $cashiers = User::whereHas('charges', function ($query) {
            $query->whereIn('status', ['paid', 'partial']);
        })->get(['id', 'name']);

        // Get cashiers awaiting reconciliation for today
        $cashiersAwaitingReconciliation = $this->reconciliationService
            ->getCashiersAwaitingReconciliation(today());

        return Inertia::render('Billing/Reconciliation/Index', [
            'reconciliations' => $reconciliations->map(fn ($r) => [
                'id' => $r->id,
                'cashier' => $r->cashier ? [
                    'id' => $r->cashier->id,
                    'name' => $r->cashier->name,
                ] : null,
                'finance_officer' => $r->financeOfficer ? [
                    'id' => $r->financeOfficer->id,
                    'name' => $r->financeOfficer->name,
                ] : null,
                'reconciliation_date' => $r->reconciliation_date->format('Y-m-d'),
                'system_total' => (float) $r->system_total,
                'physical_count' => (float) $r->physical_count,
                'variance' => (float) $r->variance,
                'variance_reason' => $r->variance_reason,
                'status' => $r->status,
                'created_at' => $r->created_at->format('Y-m-d H:i:s'),
            ]),
            'summary' => $summary,
            'cashiers' => $cashiers,
            'cashiersAwaitingReconciliation' => $cashiersAwaitingReconciliation,
            'filters' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'cashier_id' => $cashierId,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Store a newly created reconciliation.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('reconcile', Charge::class);

        $validated = $request->validate([
            'cashier_id' => 'required|exists:users,id',
            'reconciliation_date' => 'required|date',
            'physical_count' => 'required|numeric|min:0',
            'denomination_breakdown' => 'nullable|array',
            'variance_reason' => 'nullable|string|max:1000',
        ]);

        $reconciliationDate = Carbon::parse($validated['reconciliation_date']);

        // Check if reconciliation already exists
        if ($this->reconciliationService->reconciliationExists(
            $validated['cashier_id'],
            $reconciliationDate
        )) {
            return redirect()->back()->withErrors([
                'cashier_id' => 'A reconciliation already exists for this cashier on this date.',
            ]);
        }

        // Get system total
        $cashier = User::findOrFail($validated['cashier_id']);
        $systemTotal = $this->reconciliationService->getSystemTotal($cashier, $reconciliationDate);

        // Calculate variance
        $variance = $this->reconciliationService->calculateVariance(
            $systemTotal,
            (float) $validated['physical_count']
        );

        // Require reason if variance exists
        if (abs($variance) >= 0.01 && empty($validated['variance_reason'])) {
            return redirect()->back()->withErrors([
                'variance_reason' => 'A reason is required when there is a variance.',
            ]);
        }

        // Create reconciliation
        $this->reconciliationService->createReconciliation([
            'cashier_id' => $validated['cashier_id'],
            'finance_officer_id' => $request->user()->id,
            'reconciliation_date' => $reconciliationDate->format('Y-m-d'),
            'system_total' => $systemTotal,
            'physical_count' => $validated['physical_count'],
            'variance_reason' => $validated['variance_reason'] ?? null,
            'denomination_breakdown' => $validated['denomination_breakdown'] ?? null,
        ]);

        return redirect()->route('billing.accounts.reconciliation.index')
            ->with('success', 'Reconciliation created successfully.');
    }

    /**
     * Get system total for a cashier on a specific date (API endpoint).
     */
    public function getSystemTotal(Request $request): array
    {
        $this->authorize('reconcile', Charge::class);

        $validated = $request->validate([
            'cashier_id' => 'required|exists:users,id',
            'date' => 'required|date',
        ]);

        $cashier = User::findOrFail($validated['cashier_id']);
        $date = Carbon::parse($validated['date']);

        $systemTotal = $this->reconciliationService->getSystemTotal($cashier, $date);

        // Check if already reconciled
        $alreadyReconciled = $this->reconciliationService->reconciliationExists(
            $validated['cashier_id'],
            $date
        );

        return [
            'system_total' => $systemTotal,
            'already_reconciled' => $alreadyReconciled,
        ];
    }
}
