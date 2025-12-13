<?php

namespace App\Services\Dashboard;

use App\Models\ClaimBatch;
use App\Models\InsuranceClaim;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Dashboard widget for insurance officer metrics and data.
 *
 * Provides claims vetting metrics, submission tracking, and approval statistics
 * for users with insurance permissions.
 */
class InsuranceDashboard extends AbstractDashboardWidget
{
    /**
     * Get the unique identifier for this widget.
     */
    public function getWidgetId(): string
    {
        return 'claims_metrics';
    }

    /**
     * Get the required permissions to view this widget.
     *
     * @return array<string>
     */
    public function getRequiredPermissions(): array
    {
        return ['insurance.view', 'insurance.vet-claims'];
    }

    /**
     * Get metrics data for the insurance dashboard.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(User $user): array
    {
        return $this->cacheSystem('claims_metrics_all', fn () => $this->getAllClaimsMetrics());
    }

    /**
     * Get list data for the insurance dashboard.
     *
     * @return array<string, Collection>
     */
    public function getListData(User $user): array
    {
        return [
            'pendingClaims' => $this->cacheSystem('pending_claims', fn () => $this->getPendingClaimsSimplified()),
            'recentBatches' => $this->cacheSystem('recent_batches', fn () => $this->getRecentBatchesSimplified()),
        ];
    }

    /**
     * Get all claims metrics in a single optimized query.
     *
     * @return array{
     *     pendingVetting: int,
     *     vettedReady: int,
     *     submittedThisMonth: int,
     *     totalClaimValue: float
     * }
     */
    protected function getAllClaimsMetrics(): array
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        $result = InsuranceClaim::query()
            ->selectRaw("
                SUM(CASE WHEN status = 'pending_vetting' THEN 1 ELSE 0 END) as pending_vetting_count,
                SUM(CASE WHEN status = 'vetted' THEN 1 ELSE 0 END) as vetted_count,
                SUM(CASE WHEN status = 'submitted' AND MONTH(created_at) = ? AND YEAR(created_at) = ? THEN 1 ELSE 0 END) as submitted_month_count,
                COALESCE(SUM(CASE WHEN status = 'pending_vetting' THEN total_claim_amount ELSE 0 END), 0) as pending_claim_value
            ", [$currentMonth, $currentYear])
            ->first();

        return [
            'pendingVetting' => (int) ($result->pending_vetting_count ?? 0),
            'vettedReady' => (int) ($result->vetted_count ?? 0),
            'submittedThisMonth' => (int) ($result->submitted_month_count ?? 0),
            'totalClaimValue' => (float) ($result->pending_claim_value ?? 0),
        ];
    }

    /**
     * Get simplified pending claims for dashboard display.
     *
     * @return Collection<int, array{
     *     id: int,
     *     claim_check_code: string,
     *     patient_name: string,
     *     insurance_provider: string,
     *     total_amount: float,
     *     days_pending: int
     * }>
     */
    protected function getPendingClaimsSimplified(): Collection
    {
        return InsuranceClaim::query()
            ->where('status', 'pending_vetting')
            ->with(['patientInsurance.plan.provider'])
            ->orderBy('created_at', 'asc')
            ->limit(10)
            ->get()
            ->map(function (InsuranceClaim $claim) {
                return [
                    'id' => $claim->id,
                    'claim_check_code' => $claim->claim_check_code,
                    'patient_name' => $claim->patient_surname.', '.$claim->patient_other_names,
                    'insurance_provider' => $claim->patientInsurance?->plan?->provider?->name ?? 'Unknown',
                    'total_amount' => (float) $claim->total_claim_amount,
                    'days_pending' => $claim->created_at->diffInDays(now()),
                ];
            });
    }

    /**
     * Get simplified recent batches for dashboard display.
     *
     * @return Collection<int, array{
     *     id: int,
     *     batch_number: string,
     *     name: string,
     *     status: string,
     *     total_claims: int,
     *     total_amount: float
     * }>
     */
    protected function getRecentBatchesSimplified(): Collection
    {
        return ClaimBatch::query()
            ->whereIn('status', ['submitted', 'processing', 'completed', 'finalized'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (ClaimBatch $batch) => [
                'id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'name' => $batch->name,
                'status' => $batch->status,
                'total_claims' => $batch->total_claims,
                'total_amount' => (float) $batch->total_amount,
            ]);
    }
}
