<?php

use App\Models\Charge;
use App\Models\InsuranceClaim;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Links existing charges to their insurance claims.
     * This is a one-time data fix for charges that were created before
     * the auto-linking feature was implemented.
     */
    public function up(): void
    {
        // Get the InsuranceClaimService to properly create claim items
        $claimService = app(\App\Services\InsuranceClaimService::class);

        // Find all insurance claims with patient_checkin_id
        $claims = InsuranceClaim::whereNotNull('patient_checkin_id')
            ->with('patientInsurance')
            ->get();

        $totalLinked = 0;
        $totalSkipped = 0;

        foreach ($claims as $claim) {
            // Find all charges for this check-in that aren't already linked
            $unlinkedCharges = Charge::where('patient_checkin_id', $claim->patient_checkin_id)
                ->whereNotIn('id', function ($query) use ($claim) {
                    $query->select('charge_id')
                        ->from('insurance_claim_items')
                        ->where('insurance_claim_id', $claim->id)
                        ->whereNotNull('charge_id');
                })
                ->get();

            if ($unlinkedCharges->isEmpty()) {
                continue;
            }

            // Link each charge to the claim
            try {
                $claimService->addChargesToClaim($claim, $unlinkedCharges->pluck('id')->toArray());
                $totalLinked += $unlinkedCharges->count();
            } catch (\Exception $e) {
                // Log error but continue with other claims
                \Log::warning("Failed to link charges for claim {$claim->id}: " . $e->getMessage());
                $totalSkipped += $unlinkedCharges->count();
            }
        }

        \Log::info("Historical charge linking complete: {$totalLinked} charges linked, {$totalSkipped} skipped");
    }

    /**
     * Reverse the migrations.
     * 
     * Note: This only removes items that were linked by this migration
     * (items with both charge_id and created during migration window)
     */
    public function down(): void
    {
        // We can't easily undo this since we don't track which items were
        // created by this migration vs manual vetting. We'll leave items in place.
        // If needed, a manual cleanup can be done based on timestamps.
    }
};
