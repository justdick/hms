<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Models\InsuranceClaimItem;
use App\Models\WardRound;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillWardRoundChargeDates extends Command
{
    protected $signature = 'backfill:ward-round-charge-dates
                            {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Fix charge and claim item dates for ward round prescriptions (March 2025 onward, excluding Jan/Feb)';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('DRY RUN — no changes will be made.');
        }

        // Find charges linked to ward round prescriptions where dates don't match
        $mismatched = DB::table('charges as c')
            ->join('prescriptions as p', 'p.id', '=', 'c.prescription_id')
            ->where('p.prescribable_type', (new WardRound)->getMorphClass())
            ->whereNotNull('p.prescribed_at')
            ->whereRaw('DATE(c.charged_at) != DATE(p.prescribed_at)')
            ->where('p.prescribed_at', '>=', '2025-03-01')
            ->select('c.id as charge_id', 'c.charged_at', 'p.prescribed_at', 'p.id as prescription_id')
            ->get();

        $this->info("Found {$mismatched->count()} charges with mismatched dates.");

        if ($mismatched->isEmpty()) {
            $this->info('Nothing to update.');

            return self::SUCCESS;
        }

        // Show a sample
        $this->table(
            ['Charge ID', 'Current charged_at', 'Correct prescribed_at'],
            $mismatched->take(10)->map(fn ($row) => [
                $row->charge_id,
                $row->charged_at,
                $row->prescribed_at,
            ])->toArray()
        );

        if ($mismatched->count() > 10) {
            $remaining = $mismatched->count() - 10;
            $this->info("... and {$remaining} more.");
        }

        if ($isDryRun) {
            // Count affected claim items too
            $claimItemCount = DB::table('insurance_claim_items as ici')
                ->whereIn('ici.charge_id', $mismatched->pluck('charge_id'))
                ->whereRaw('DATE(ici.item_date) != DATE((SELECT p2.prescribed_at FROM prescriptions p2 JOIN charges c2 ON c2.prescription_id = p2.id WHERE c2.id = ici.charge_id LIMIT 1))')
                ->count();

            $this->info("Would also update {$claimItemCount} insurance claim items.");

            return self::SUCCESS;
        }

        if (! $this->confirm("Update {$mismatched->count()} charges and their claim items?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $chargesUpdated = 0;
        $claimItemsUpdated = 0;

        DB::transaction(function () use ($mismatched, &$chargesUpdated, &$claimItemsUpdated) {
            foreach ($mismatched as $row) {
                Charge::where('id', $row->charge_id)
                    ->update(['charged_at' => $row->prescribed_at]);
                $chargesUpdated++;

                // Update linked claim items
                $updated = InsuranceClaimItem::where('charge_id', $row->charge_id)
                    ->update(['item_date' => $row->prescribed_at]);
                $claimItemsUpdated += $updated;
            }
        });

        $this->info("Updated {$chargesUpdated} charges and {$claimItemsUpdated} claim items.");

        return self::SUCCESS;
    }
}
