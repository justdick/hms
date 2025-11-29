<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PaymentAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CreditService
{
    /**
     * Add a credit tag to a patient.
     *
     * @param  Patient  $patient  The patient to tag
     * @param  User  $authorizedBy  The user authorizing the credit tag
     * @param  string  $reason  The reason for adding the credit tag
     * @param  string|null  $ipAddress  The IP address of the request
     * @return bool Whether the operation was successful
     */
    public function addCreditTag(Patient $patient, User $authorizedBy, string $reason, ?string $ipAddress = null): bool
    {
        if ($patient->is_credit_eligible) {
            return false; // Already credit-eligible
        }

        return DB::transaction(function () use ($patient, $authorizedBy, $reason, $ipAddress) {
            $patient->update([
                'is_credit_eligible' => true,
                'credit_reason' => $reason,
                'credit_authorized_by' => $authorizedBy->id,
                'credit_authorized_at' => now(),
            ]);

            PaymentAuditLog::logCreditTagChange($patient, $authorizedBy, true, $reason, $ipAddress);

            return true;
        });
    }

    /**
     * Remove a credit tag from a patient.
     *
     * @param  Patient  $patient  The patient to remove the tag from
     * @param  User  $removedBy  The user removing the credit tag
     * @param  string  $reason  The reason for removing the credit tag
     * @param  string|null  $ipAddress  The IP address of the request
     * @return bool Whether the operation was successful
     */
    public function removeCreditTag(Patient $patient, User $removedBy, string $reason, ?string $ipAddress = null): bool
    {
        if (! $patient->is_credit_eligible) {
            return false; // Not credit-eligible
        }

        return DB::transaction(function () use ($patient, $removedBy, $reason, $ipAddress) {
            $patient->update([
                'is_credit_eligible' => false,
                'credit_reason' => null,
                'credit_authorized_by' => null,
                'credit_authorized_at' => null,
            ]);

            PaymentAuditLog::logCreditTagChange($patient, $removedBy, false, $reason, $ipAddress);

            return true;
        });
    }

    /**
     * Get all credit-eligible patients with their total owing amounts.
     *
     * @return Collection<Patient>
     */
    public function getCreditPatients(): Collection
    {
        return Patient::creditEligible()
            ->with(['creditAuthorizedByUser:id,name'])
            ->withSum(['checkins as total_owing' => function ($query) {
                $query->join('charges', 'patient_checkins.id', '=', 'charges.patient_checkin_id')
                    ->where('charges.status', 'owing');
            }], 'charges.amount')
            ->get();
    }

    /**
     * Get the total owing amount for a specific patient.
     *
     * @param  Patient  $patient  The patient to check
     * @return float The total owing amount
     */
    public function getPatientOwingAmount(Patient $patient): float
    {
        return $patient->checkins()
            ->join('charges', 'patient_checkins.id', '=', 'charges.patient_checkin_id')
            ->where('charges.status', 'owing')
            ->sum('charges.amount');
    }

    /**
     * Check if a patient is credit-eligible.
     *
     * @param  Patient  $patient  The patient to check
     * @return bool Whether the patient is credit-eligible
     */
    public function isCreditEligible(Patient $patient): bool
    {
        return $patient->is_credit_eligible === true;
    }
}
