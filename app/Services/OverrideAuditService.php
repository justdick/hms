<?php

namespace App\Services;

use App\Models\BillAdjustment;
use App\Models\PatientCheckin;
use App\Models\ServiceAccessOverride;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class OverrideAuditService
{
    /**
     * Log a service access override activation.
     */
    public function logOverrideActivation(ServiceAccessOverride $override): void
    {
        Log::channel('billing_audit')->info('Service access override activated', [
            'override_id' => $override->id,
            'patient_checkin_id' => $override->patient_checkin_id,
            'service_type' => $override->service_type,
            'service_code' => $override->service_code,
            'reason' => $override->reason,
            'authorized_by' => $override->authorized_by,
            'authorized_by_name' => $override->authorizedBy->name ?? 'Unknown',
            'authorized_at' => $override->authorized_at->toDateTimeString(),
            'expires_at' => $override->expires_at->toDateTimeString(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Log a service access override deactivation.
     */
    public function logOverrideDeactivation(ServiceAccessOverride $override, int $deactivatedBy): void
    {
        Log::channel('billing_audit')->info('Service access override deactivated', [
            'override_id' => $override->id,
            'patient_checkin_id' => $override->patient_checkin_id,
            'service_type' => $override->service_type,
            'deactivated_by' => $deactivatedBy,
            'deactivated_at' => now()->toDateTimeString(),
            'original_expiry' => $override->expires_at->toDateTimeString(),
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Log a bill adjustment (waiver or discount).
     */
    public function logBillAdjustment(BillAdjustment $adjustment): void
    {
        Log::channel('billing_audit')->info('Bill adjustment applied', [
            'adjustment_id' => $adjustment->id,
            'charge_id' => $adjustment->charge_id,
            'adjustment_type' => $adjustment->adjustment_type,
            'original_amount' => $adjustment->original_amount,
            'adjustment_amount' => $adjustment->adjustment_amount,
            'final_amount' => $adjustment->final_amount,
            'reason' => $adjustment->reason,
            'adjusted_by' => $adjustment->adjusted_by,
            'adjusted_by_name' => $adjustment->adjustedBy->name ?? 'Unknown',
            'adjusted_at' => $adjustment->adjusted_at->toDateTimeString(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Retrieve complete override history for a patient check-in.
     */
    public function getOverrideHistory(PatientCheckin $checkin): Collection
    {
        $overrides = ServiceAccessOverride::where('patient_checkin_id', $checkin->id)
            ->with('authorizedBy:id,name')
            ->orderBy('authorized_at', 'desc')
            ->get()
            ->map(function ($override) {
                return [
                    'id' => $override->id,
                    'type' => 'service_override',
                    'service_type' => $override->service_type,
                    'service_code' => $override->service_code,
                    'reason' => $override->reason,
                    'authorized_by' => $override->authorizedBy->name ?? 'Unknown',
                    'authorized_at' => $override->authorized_at,
                    'expires_at' => $override->expires_at,
                    'is_active' => $override->is_active && ! $override->isExpired(),
                    'is_expired' => $override->isExpired(),
                    'remaining_duration' => $override->getRemainingDuration(),
                ];
            });

        $adjustments = BillAdjustment::whereHas('charge', function ($query) use ($checkin) {
            $query->where('patient_checkin_id', $checkin->id);
        })
            ->with(['adjustedBy:id,name', 'charge:id,description,service_type'])
            ->orderBy('adjusted_at', 'desc')
            ->get()
            ->map(function ($adjustment) {
                return [
                    'id' => $adjustment->id,
                    'type' => 'bill_adjustment',
                    'adjustment_type' => $adjustment->adjustment_type,
                    'charge_description' => $adjustment->charge->description ?? 'Unknown',
                    'service_type' => $adjustment->charge->service_type ?? null,
                    'original_amount' => $adjustment->original_amount,
                    'adjustment_amount' => $adjustment->adjustment_amount,
                    'final_amount' => $adjustment->final_amount,
                    'reason' => $adjustment->reason,
                    'adjusted_by' => $adjustment->adjustedBy->name ?? 'Unknown',
                    'adjusted_at' => $adjustment->adjusted_at,
                ];
            });

        return $overrides->concat($adjustments)->sortByDesc(function ($item) {
            return $item['authorized_at'] ?? $item['adjusted_at'];
        })->values();
    }

    /**
     * Check if there are any active overrides for a patient check-in.
     */
    public function hasActiveOverrides(PatientCheckin $checkin, ?string $serviceType = null): bool
    {
        $query = ServiceAccessOverride::active()
            ->where('patient_checkin_id', $checkin->id);

        if ($serviceType) {
            $query->forService($serviceType);
        }

        return $query->exists();
    }

    /**
     * Get all active overrides for a patient check-in.
     */
    public function getActiveOverrides(PatientCheckin $checkin, ?string $serviceType = null): Collection
    {
        $query = ServiceAccessOverride::active()
            ->where('patient_checkin_id', $checkin->id)
            ->with('authorizedBy:id,name');

        if ($serviceType) {
            $query->forService($serviceType);
        }

        return $query->get()->map(function ($override) {
            return [
                'id' => $override->id,
                'service_type' => $override->service_type,
                'service_code' => $override->service_code,
                'reason' => $override->reason,
                'authorized_by' => $override->authorizedBy->name ?? 'Unknown',
                'authorized_at' => $override->authorized_at,
                'expires_at' => $override->expires_at,
                'remaining_duration' => $override->getRemainingDuration(),
            ];
        });
    }

    /**
     * Get override statistics for reporting.
     */
    public function getOverrideStatistics(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $overrides = ServiceAccessOverride::whereBetween('authorized_at', [$startDate, $endDate])
            ->with('authorizedBy:id,name')
            ->get();

        $adjustments = BillAdjustment::whereBetween('adjusted_at', [$startDate, $endDate])
            ->with('adjustedBy:id,name')
            ->get();

        return [
            'total_overrides' => $overrides->count(),
            'overrides_by_service' => $overrides->groupBy('service_type')->map->count(),
            'overrides_by_user' => $overrides->groupBy('authorized_by')->map->count(),
            'total_adjustments' => $adjustments->count(),
            'adjustments_by_type' => $adjustments->groupBy('adjustment_type')->map->count(),
            'total_waived_amount' => $adjustments->where('adjustment_type', 'waiver')->sum('adjustment_amount'),
            'total_discount_amount' => $adjustments->whereIn('adjustment_type', ['discount_percentage', 'discount_fixed'])->sum('adjustment_amount'),
            'adjustments_by_user' => $adjustments->groupBy('adjusted_by')->map->count(),
        ];
    }
}
