<?php

namespace App\Services\Dashboard;

use App\Models\Charge;
use App\Models\InsuranceClaim;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard widget for finance officer metrics and data.
 *
 * Provides revenue metrics, outstanding receivables, pending insurance claims,
 * and revenue breakdown by payment method for users with finance permissions.
 */
class FinanceDashboard extends AbstractDashboardWidget
{
    /**
     * Get the unique identifier for this widget.
     */
    public function getWidgetId(): string
    {
        return 'finance_metrics';
    }

    /**
     * Get the required permissions to view this widget.
     *
     * @return array<string>
     */
    public function getRequiredPermissions(): array
    {
        return ['billing.reports', 'billing.reconcile'];
    }

    /**
     * Get metrics data for the finance dashboard.
     * Optimized to combine related queries.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(User $user): array
    {
        // Finance metrics are system-wide aggregates (5 min cache)
        // Combine charge-related metrics into single query
        $chargeMetrics = $this->cacheSystem('charge_metrics', fn () => $this->getChargeMetrics());

        // Combine insurance-related metrics into single query
        $insuranceMetrics = $this->cacheSystem('insurance_metrics', fn () => $this->getInsuranceMetrics());

        return [
            'todayRevenue' => $chargeMetrics['todayRevenue'],
            'outstandingReceivables' => $chargeMetrics['outstandingReceivables'],
            'pendingInsuranceClaims' => $insuranceMetrics['amount'],
            'pendingInsuranceClaimsCount' => $insuranceMetrics['count'],
        ];
    }

    /**
     * Get list data for the finance dashboard.
     *
     * @return array<string, Collection>
     */
    public function getListData(User $user): array
    {
        return [
            'revenueByPaymentMethod' => $this->cacheSystem('revenue_by_payment_method', fn () => $this->getRevenueByPaymentMethod()),
        ];
    }

    /**
     * Get charge-related metrics in optimized queries.
     *
     * @return array{todayRevenue: float, outstandingReceivables: float}
     */
    protected function getChargeMetrics(): array
    {
        $today = today()->toDateString();

        // Use a single query with conditional aggregation
        $result = Charge::query()
            ->notVoided()
            ->selectRaw("
                COALESCE(SUM(CASE WHEN DATE(paid_at) = ? AND status IN ('paid', 'partial') THEN paid_amount ELSE 0 END), 0) as today_revenue,
                COALESCE(SUM(CASE WHEN status IN ('pending', 'owing', 'partial') THEN (amount - COALESCE(paid_amount, 0)) ELSE 0 END), 0) as outstanding
            ", [$today])
            ->first();

        return [
            'todayRevenue' => (float) ($result->today_revenue ?? 0),
            'outstandingReceivables' => (float) ($result->outstanding ?? 0),
        ];
    }

    /**
     * Get insurance-related metrics in a single query.
     *
     * @return array{amount: float, count: int}
     */
    protected function getInsuranceMetrics(): array
    {
        $result = InsuranceClaim::query()
            ->whereIn('status', ['pending_vetting', 'vetted', 'submitted'])
            ->selectRaw('COALESCE(SUM(total_claim_amount), 0) as total_amount, COUNT(*) as total_count')
            ->first();

        return [
            'amount' => (float) ($result->total_amount ?? 0),
            'count' => (int) ($result->total_count ?? 0),
        ];
    }

    /**
     * Get revenue breakdown by payment method for today.
     *
     * @return Collection<int, array{
     *     payment_method: string,
     *     transaction_count: int,
     *     total_amount: float,
     *     percentage: float
     * }>
     */
    protected function getRevenueByPaymentMethod(): Collection
    {
        // Get revenue grouped by charge_type (which represents payment method)
        $breakdown = Charge::query()
            ->whereDate('paid_at', today())
            ->whereIn('status', ['paid', 'partial'])
            ->notVoided()
            ->select(
                'charge_type',
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(paid_amount) as total_amount')
            )
            ->groupBy('charge_type')
            ->orderByDesc('total_amount')
            ->get();

        // Calculate total revenue from the breakdown
        $todayRevenue = $breakdown->sum('total_amount');

        return $breakdown->map(function ($item) use ($todayRevenue) {
            $totalAmount = (float) $item->total_amount;
            $percentage = $todayRevenue > 0 ? round(($totalAmount / $todayRevenue) * 100, 1) : 0;

            return [
                'payment_method' => $this->formatPaymentMethod($item->charge_type),
                'payment_method_key' => $item->charge_type ?? 'other',
                'transaction_count' => (int) $item->transaction_count,
                'total_amount' => $totalAmount,
                'percentage' => $percentage,
            ];
        });
    }

    /**
     * Format payment method for display.
     */
    protected function formatPaymentMethod(?string $chargeType): string
    {
        if (! $chargeType) {
            return 'Other';
        }

        $methods = [
            'cash' => 'Cash',
            'card' => 'Card',
            'mobile_money' => 'Mobile Money',
            'momo' => 'Mobile Money',
            'insurance' => 'Insurance',
            'bank_transfer' => 'Bank Transfer',
            'credit' => 'Credit',
            'cheque' => 'Cheque',
        ];

        return $methods[strtolower($chargeType)] ?? ucfirst(str_replace('_', ' ', $chargeType));
    }
}
