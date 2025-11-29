<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\PaymentAuditLog;
use App\Models\Reconciliation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReconciliationService
{
    /**
     * Get system total for a cashier on a specific date.
     * This is the sum of all cash payments processed by the cashier.
     */
    public function getSystemTotal(User $cashier, Carbon $date): float
    {
        return (float) Charge::where('processed_by', $cashier->id)
            ->whereDate('paid_at', $date)
            ->whereIn('status', ['paid', 'partial'])
            ->whereRaw("COALESCE(metadata->>'payment_method', 'cash') = 'cash'")
            ->sum('paid_amount');
    }

    /**
     * Calculate variance between physical count and system total.
     * Positive variance means more cash than expected (overage).
     * Negative variance means less cash than expected (shortage).
     */
    public function calculateVariance(float $systemTotal, float $physicalCount): float
    {
        return round($physicalCount - $systemTotal, 2);
    }

    /**
     * Create a new reconciliation record.
     */
    public function createReconciliation(array $data): Reconciliation
    {
        return DB::transaction(function () use ($data) {
            $systemTotal = (float) $data['system_total'];
            $physicalCount = (float) $data['physical_count'];
            $variance = $this->calculateVariance($systemTotal, $physicalCount);

            // Determine status based on variance
            $status = abs($variance) < 0.01 ? 'balanced' : 'variance';

            $reconciliation = Reconciliation::create([
                'cashier_id' => $data['cashier_id'],
                'finance_officer_id' => $data['finance_officer_id'],
                'reconciliation_date' => $data['reconciliation_date'],
                'system_total' => $systemTotal,
                'physical_count' => $physicalCount,
                'variance' => $variance,
                'variance_reason' => $data['variance_reason'] ?? null,
                'denomination_breakdown' => $data['denomination_breakdown'] ?? null,
                'status' => $status,
            ]);

            // Create audit log entry
            PaymentAuditLog::create([
                'user_id' => $data['finance_officer_id'],
                'action' => 'reconciliation_created',
                'new_values' => [
                    'reconciliation_id' => $reconciliation->id,
                    'cashier_id' => $data['cashier_id'],
                    'reconciliation_date' => $data['reconciliation_date'],
                    'system_total' => $systemTotal,
                    'physical_count' => $physicalCount,
                    'variance' => $variance,
                    'status' => $status,
                ],
                'reason' => $data['variance_reason'] ?? null,
            ]);

            return $reconciliation;
        });
    }

    /**
     * Get reconciliation history with optional filters.
     */
    public function getReconciliationHistory(array $filters = []): Collection
    {
        $query = Reconciliation::with(['cashier:id,name', 'financeOfficer:id,name'])
            ->orderBy('reconciliation_date', 'desc')
            ->orderBy('created_at', 'desc');

        // Filter by cashier
        if (! empty($filters['cashier_id'])) {
            $query->where('cashier_id', $filters['cashier_id']);
        }

        // Filter by date range
        if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
            $query->whereBetween('reconciliation_date', [
                Carbon::parse($filters['start_date'])->startOfDay(),
                Carbon::parse($filters['end_date'])->endOfDay(),
            ]);
        } elseif (! empty($filters['date'])) {
            $query->whereDate('reconciliation_date', $filters['date']);
        }

        // Filter by status
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get();
    }

    /**
     * Check if a reconciliation already exists for a cashier on a specific date.
     */
    public function reconciliationExists(int $cashierId, Carbon $date): bool
    {
        return Reconciliation::where('cashier_id', $cashierId)
            ->whereDate('reconciliation_date', $date)
            ->exists();
    }

    /**
     * Get a specific reconciliation by ID.
     */
    public function getReconciliation(int $id): ?Reconciliation
    {
        return Reconciliation::with(['cashier:id,name', 'financeOfficer:id,name'])
            ->find($id);
    }

    /**
     * Get cashiers who have collections on a specific date but no reconciliation yet.
     */
    public function getCashiersAwaitingReconciliation(Carbon $date): Collection
    {
        // Get cashiers with collections on the date
        $cashiersWithCollections = Charge::whereDate('paid_at', $date)
            ->whereIn('status', ['paid', 'partial'])
            ->whereNotNull('processed_by')
            ->whereRaw("COALESCE(metadata->>'payment_method', 'cash') = 'cash'")
            ->select('processed_by')
            ->distinct()
            ->pluck('processed_by');

        // Get cashiers already reconciled
        $reconciledCashiers = Reconciliation::whereDate('reconciliation_date', $date)
            ->pluck('cashier_id');

        // Return cashiers awaiting reconciliation
        $awaitingIds = $cashiersWithCollections->diff($reconciledCashiers);

        return User::whereIn('id', $awaitingIds)
            ->get(['id', 'name'])
            ->map(function ($user) use ($date) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'system_total' => $this->getSystemTotal($user, $date),
                ];
            });
    }

    /**
     * Get reconciliation summary statistics for a date range.
     */
    public function getReconciliationSummary(Carbon $startDate, Carbon $endDate): array
    {
        $reconciliations = Reconciliation::whereBetween('reconciliation_date', [
            $startDate->startOfDay(),
            $endDate->endOfDay(),
        ])->get();

        return [
            'total_count' => $reconciliations->count(),
            'balanced_count' => $reconciliations->where('status', 'balanced')->count(),
            'variance_count' => $reconciliations->where('status', 'variance')->count(),
            'total_system_amount' => $reconciliations->sum('system_total'),
            'total_physical_amount' => $reconciliations->sum('physical_count'),
            'total_variance' => $reconciliations->sum('variance'),
            'average_variance' => $reconciliations->count() > 0
                ? $reconciliations->avg('variance')
                : 0,
        ];
    }
}
