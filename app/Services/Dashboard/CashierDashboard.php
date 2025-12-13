<?php

namespace App\Services\Dashboard;

use App\Models\Charge;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Dashboard widget for cashier metrics and data.
 *
 * Provides payment metrics, pending payments, and recent payment activity
 * for users with billing collection permissions.
 */
class CashierDashboard extends AbstractDashboardWidget
{
    /**
     * Get the unique identifier for this widget.
     */
    public function getWidgetId(): string
    {
        return 'billing_metrics';
    }

    /**
     * Get the required permissions to view this widget.
     *
     * @return array<string>
     */
    public function getRequiredPermissions(): array
    {
        return ['billing.collect', 'billing.view-all'];
    }

    /**
     * Get metrics data for the cashier dashboard.
     * Optimized to combine related queries where possible.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(User $user): array
    {
        // User-specific collections use per-user cache (60 sec) - combined into single query
        $userMetrics = $this->cacheForUser($user, 'user_metrics', fn () => $this->getUserMetrics($user));
        $pendingCount = $this->cacheSystem('pending_count', fn () => $this->getPendingPaymentsCount());

        return [
            'todayCollections' => $userMetrics['collections'],
            'transactionCount' => $userMetrics['transactions'],
            'pendingPayments' => $pendingCount,
            'averageTransaction' => $userMetrics['transactions'] > 0
                ? $userMetrics['collections'] / $userMetrics['transactions']
                : 0,
        ];
    }

    /**
     * Get list data for the cashier dashboard.
     *
     * @return array<string, Collection>
     */
    public function getListData(User $user): array
    {
        return [
            'recentPayments' => $this->cacheForUser($user, 'recent_payments', fn () => $this->getRecentPaymentsSimplified($user)),
        ];
    }

    /**
     * Get count of pending payments.
     */
    protected function getPendingPaymentsCount(): int
    {
        return Charge::query()
            ->pending()
            ->notVoided()
            ->count();
    }

    /**
     * Get user-specific metrics in a single query.
     *
     * @return array{collections: float, transactions: int}
     */
    protected function getUserMetrics(User $user): array
    {
        $query = Charge::query()
            ->whereDate('paid_at', today())
            ->whereIn('status', ['paid', 'partial']);

        // If user doesn't have view-all permission, only show their own data
        if (! $user->can('billing.view-all')) {
            $query->where('processed_by', $user->id);
        }

        $result = $query
            ->selectRaw('COALESCE(SUM(paid_amount), 0) as total_collections, COUNT(*) as total_transactions')
            ->first();

        return [
            'collections' => (float) ($result->total_collections ?? 0),
            'transactions' => (int) ($result->total_transactions ?? 0),
        ];
    }

    /**
     * Get simplified recent payments for dashboard display.
     *
     * @return Collection<int, array{
     *     id: int,
     *     patient_name: string,
     *     amount: float,
     *     method: string,
     *     time: string
     * }>
     */
    protected function getRecentPaymentsSimplified(User $user): Collection
    {
        $query = Charge::query()
            ->whereDate('paid_at', today())
            ->whereIn('status', ['paid', 'partial'])
            ->with(['patientCheckin.patient']);

        if (! $user->can('billing.view-all')) {
            $query->where('processed_by', $user->id);
        }

        return $query
            ->orderByDesc('paid_at')
            ->limit(10)
            ->get()
            ->map(fn (Charge $charge) => [
                'id' => $charge->id,
                'patient_name' => $charge->patientCheckin?->patient?->full_name ?? 'Unknown',
                'amount' => (float) $charge->paid_amount,
                'method' => $charge->payment_method ?? 'cash',
                'time' => $charge->paid_at?->format('H:i'),
            ]);
    }
}
