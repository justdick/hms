<?php

namespace App\Services;

use App\Models\BillingOverride;
use App\Models\Charge;
use App\Models\PatientCheckin;
use App\Models\PaymentAuditLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OverrideService
{
    /**
     * Create a billing override for a charge, marking it as owing.
     *
     * @param  Charge  $charge  The charge to override
     * @param  User  $authorizedBy  The user authorizing the override
     * @param  string  $reason  The reason for the override
     * @param  int|null  $expiryHours  Hours until override expires (null = no expiry)
     * @return BillingOverride The created override record
     */
    public function createOverride(
        Charge $charge,
        User $authorizedBy,
        string $reason,
        ?int $expiryHours = null
    ): BillingOverride {
        return DB::transaction(function () use ($charge, $authorizedBy, $reason, $expiryHours) {
            // Create the billing override record
            $override = BillingOverride::create([
                'patient_checkin_id' => $charge->patient_checkin_id,
                'charge_id' => $charge->id,
                'authorized_by' => $authorizedBy->id,
                'service_type' => $charge->service_type,
                'reason' => $reason,
                'status' => BillingOverride::STATUS_ACTIVE,
                'authorized_at' => now(),
                'expires_at' => $expiryHours ? now()->addHours($expiryHours) : null,
            ]);

            // Mark the charge as owing
            $charge->markAsOwing($reason);

            // Create audit log entry
            PaymentAuditLog::logOverride($charge, $authorizedBy, $reason, request()->ip());

            // Log for monitoring
            Log::channel('billing_audit')->info('Billing override created', [
                'override_id' => $override->id,
                'charge_id' => $charge->id,
                'patient_checkin_id' => $charge->patient_checkin_id,
                'service_type' => $charge->service_type,
                'amount' => $charge->amount,
                'authorized_by' => $authorizedBy->id,
                'authorized_by_name' => $authorizedBy->name,
                'reason' => $reason,
                'ip_address' => request()->ip(),
            ]);

            return $override;
        });
    }

    /**
     * Create overrides for multiple charges at once.
     *
     * @param  array  $chargeIds  Array of charge IDs to override
     * @param  User  $authorizedBy  The user authorizing the override
     * @param  string  $reason  The reason for the override
     * @param  int|null  $expiryHours  Hours until override expires
     * @return Collection Collection of created overrides
     */
    public function createOverridesForCharges(
        array $chargeIds,
        User $authorizedBy,
        string $reason,
        ?int $expiryHours = null
    ): Collection {
        $overrides = collect();

        $charges = Charge::whereIn('id', $chargeIds)
            ->where('status', 'pending')
            ->get();

        foreach ($charges as $charge) {
            $override = $this->createOverride($charge, $authorizedBy, $reason, $expiryHours);
            $overrides->push($override);
        }

        return $overrides;
    }

    /**
     * Check the override status for a charge.
     *
     * @param  Charge  $charge  The charge to check
     * @return array Status information including whether override exists and is active
     */
    public function checkOverrideStatus(Charge $charge): array
    {
        $override = BillingOverride::where('charge_id', $charge->id)
            ->latest('authorized_at')
            ->first();

        if (! $override) {
            return [
                'has_override' => false,
                'is_active' => false,
                'override' => null,
            ];
        }

        return [
            'has_override' => true,
            'is_active' => $override->isActive(),
            'is_used' => $override->isUsed(),
            'is_expired' => $override->isExpired(),
            'override' => [
                'id' => $override->id,
                'service_type' => $override->service_type,
                'reason' => $override->reason,
                'status' => $override->status,
                'authorized_by' => $override->authorizedByUser?->name ?? 'Unknown',
                'authorized_at' => $override->authorized_at?->format('M j, Y g:i A'),
                'expires_at' => $override->expires_at?->format('M j, Y g:i A'),
            ],
        ];
    }

    /**
     * Check if a patient checkin has any active billing overrides.
     *
     * @param  PatientCheckin  $checkin  The patient checkin to check
     * @param  string|null  $serviceType  Optional service type filter
     * @return bool Whether active overrides exist
     */
    public function hasActiveOverride(PatientCheckin $checkin, ?string $serviceType = null): bool
    {
        $query = BillingOverride::active()
            ->forCheckin($checkin->id);

        if ($serviceType) {
            $query->forServiceType($serviceType);
        }

        return $query->exists();
    }

    /**
     * Get all active billing overrides for a patient checkin.
     *
     * @param  PatientCheckin  $checkin  The patient checkin
     * @param  string|null  $serviceType  Optional service type filter
     * @return Collection Collection of active overrides
     */
    public function getActiveOverrides(PatientCheckin $checkin, ?string $serviceType = null): Collection
    {
        $query = BillingOverride::active()
            ->forCheckin($checkin->id)
            ->with(['authorizedByUser:id,name', 'charge:id,description,amount,service_type']);

        if ($serviceType) {
            $query->forServiceType($serviceType);
        }

        return $query->get()->map(function ($override) {
            return [
                'id' => $override->id,
                'charge_id' => $override->charge_id,
                'service_type' => $override->service_type,
                'reason' => $override->reason,
                'status' => $override->status,
                'charge' => $override->charge ? [
                    'id' => $override->charge->id,
                    'description' => $override->charge->description,
                    'amount' => $override->charge->amount,
                ] : null,
                'authorized_by' => [
                    'id' => $override->authorizedByUser?->id,
                    'name' => $override->authorizedByUser?->name ?? 'Unknown',
                ],
                'authorized_at' => $override->authorized_at?->format('M j, Y g:i A'),
                'expires_at' => $override->expires_at?->format('M j, Y g:i A'),
            ];
        });
    }

    /**
     * Get all owing charges for a patient checkin.
     *
     * @param  PatientCheckin  $checkin  The patient checkin
     * @return Collection Collection of owing charges
     */
    public function getOwingCharges(PatientCheckin $checkin): Collection
    {
        return Charge::forPatient($checkin->id)
            ->owing()
            ->with(['patientCheckin.patient:id,first_name,last_name,patient_number'])
            ->get();
    }

    /**
     * Get total owing amount for a patient checkin.
     *
     * @param  PatientCheckin  $checkin  The patient checkin
     * @return float Total owing amount
     */
    public function getTotalOwingAmount(PatientCheckin $checkin): float
    {
        return Charge::forPatient($checkin->id)
            ->owing()
            ->sum('amount');
    }

    /**
     * Mark an override as used (when the service has been rendered).
     *
     * @param  BillingOverride  $override  The override to mark as used
     */
    public function markOverrideAsUsed(BillingOverride $override): void
    {
        $override->markAsUsed();

        Log::channel('billing_audit')->info('Billing override marked as used', [
            'override_id' => $override->id,
            'charge_id' => $override->charge_id,
            'service_type' => $override->service_type,
        ]);
    }

    /**
     * Expire an override manually.
     *
     * @param  BillingOverride  $override  The override to expire
     */
    public function expireOverride(BillingOverride $override): void
    {
        $override->markAsExpired();

        Log::channel('billing_audit')->info('Billing override expired manually', [
            'override_id' => $override->id,
            'charge_id' => $override->charge_id,
            'expired_by' => auth()->id(),
        ]);
    }
}
