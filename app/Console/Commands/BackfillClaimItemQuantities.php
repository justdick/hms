<?php

namespace App\Console\Commands;

use App\Models\InsuranceClaimItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillClaimItemQuantities extends Command
{
    protected $signature = 'claims:backfill-quantities
                            {--dry-run : Show what would be updated without making changes}
                            {--status= : Only fix items on claims with this status (e.g. draft, pending_vetting, vetted)}';

    protected $description = 'Fix drug claim item quantities that were incorrectly set to 1 due to metadata bug';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $statusFilter = $this->option('status');

        if ($isDryRun) {
            $this->info('DRY RUN — no changes will be made.');
        }

        // Find all drug claim items with charge_id where quantity doesn't match prescription
        $query = InsuranceClaimItem::query()
            ->where('item_type', 'drug')
            ->whereNotNull('charge_id')
            ->whereHas('charge', function ($q) {
                $q->whereNotNull('prescription_id')
                    ->whereHas('prescription', function ($pq) {
                        // Only items where claim qty differs from prescription qty
                        $pq->whereColumn('insurance_claim_items.quantity', '!=', 'prescriptions.quantity');
                    });
            });

        if ($statusFilter) {
            $query->whereHas('claim', function ($q) use ($statusFilter) {
                $q->where('status', $statusFilter);
            });
        }

        // Use a direct join query for accuracy since whereColumn across relations is tricky
        $items = DB::table('insurance_claim_items as ici')
            ->join('charges as c', 'ici.charge_id', '=', 'c.id')
            ->join('prescriptions as p', 'c.prescription_id', '=', 'p.id')
            ->join('insurance_claims as ic', 'ici.insurance_claim_id', '=', 'ic.id')
            ->leftJoin('drugs as d', 'p.drug_id', '=', 'd.id')
            ->where('ici.item_type', 'drug')
            ->whereColumn('ici.quantity', '!=', DB::raw('COALESCE(p.quantity_to_dispense, p.quantity)'))
            ->when($statusFilter, function ($q) use ($statusFilter) {
                $q->where('ic.status', $statusFilter);
            })
            ->select([
                'ici.id',
                'ici.description',
                'ici.quantity as current_qty',
                'ici.nhis_price',
                'ici.unit_tariff',
                'ici.is_covered',
                'ic.id as claim_id',
                'ic.status as claim_status',
                DB::raw('COALESCE(p.quantity_to_dispense, p.quantity) as correct_qty'),
                DB::raw('COALESCE(d.nhis_claim_qty_as_one, 0) as nhis_claim_qty_as_one'),
            ])
            ->get();

        if ($items->isEmpty()) {
            $this->info('No items need updating.');

            return self::SUCCESS;
        }

        // Group by claim status for reporting
        $byStatus = $items->groupBy('claim_status');
        $this->newLine();
        $this->info('Items needing quantity correction:');
        foreach ($byStatus as $status => $statusItems) {
            $this->line("  {$status}: {$statusItems->count()} items");
        }
        $this->newLine();

        if ($isDryRun) {
            // Show sample of what would change
            $this->table(
                ['ID', 'Description', 'Current Qty', 'Correct Qty', 'Claim Status', 'NHIS Qty=1'],
                $items->take(20)->map(fn ($item) => [
                    $item->id,
                    \Illuminate\Support\Str::limit($item->description, 50),
                    $item->current_qty,
                    $item->nhis_claim_qty_as_one ? '1 (NHIS override)' : $item->correct_qty,
                    $item->claim_status,
                    $item->nhis_claim_qty_as_one ? 'Yes' : 'No',
                ])->toArray()
            );

            if ($items->count() > 20) {
                $this->line('  ... and '.($items->count() - 20).' more items');
            }

            $this->newLine();
            $this->info("Total: {$items->count()} items would be updated.");
            $this->line('Run without --dry-run to apply changes.');

            return self::SUCCESS;
        }

        // Confirm before proceeding
        if (! $this->confirm("Update {$items->count()} claim item quantities?")) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $updated = 0;
        $skipped = 0;
        $skippedItems = collect();
        $claimsToRecalculate = collect();

        $this->withProgressBar($items, function ($item) use (&$updated, &$skipped, &$skippedItems, &$claimsToRecalculate) {
            // Skip drugs flagged as nhis_claim_qty_as_one — those should stay at 1
            if ($item->nhis_claim_qty_as_one && $item->current_qty == 1) {
                $skipped++;
                $skippedItems->push([
                    'id' => $item->id,
                    'claim_id' => $item->claim_id,
                    'description' => $item->description,
                    'current_qty' => $item->current_qty,
                    'correct_qty' => $item->correct_qty,
                    'unit_price' => $item->nhis_price ?? $item->unit_tariff,
                    'reason' => 'NHIS claim qty as one flag',
                ]);

                return;
            }

            $correctQty = $item->nhis_claim_qty_as_one ? 1 : (int) $item->correct_qty;

            // Skip if already correct
            if ((int) $item->current_qty === $correctQty) {
                $skipped++;
                $skippedItems->push([
                    'id' => $item->id,
                    'claim_id' => $item->claim_id,
                    'description' => $item->description,
                    'current_qty' => $item->current_qty,
                    'correct_qty' => $correctQty,
                    'unit_price' => $item->nhis_price ?? $item->unit_tariff,
                    'reason' => 'Already correct',
                ]);

                return;
            }

            $unitPrice = $item->nhis_price ?? $item->unit_tariff;
            $subtotal = $unitPrice * $correctQty;
            
            // Skip if subtotal exceeds DECIMAL(10,2) limit (99,999,999.99)
            if ($subtotal > 99999999.99) {
                $skipped++;
                $skippedItems->push([
                    'id' => $item->id,
                    'claim_id' => $item->claim_id,
                    'description' => $item->description,
                    'current_qty' => $item->current_qty,
                    'correct_qty' => $correctQty,
                    'unit_price' => $unitPrice,
                    'calculated_subtotal' => $subtotal,
                    'reason' => 'Subtotal exceeds DECIMAL(10,2) limit',
                ]);

                return;
            }

            $insurancePays = $item->is_covered ? $subtotal : 0;
            $patientPays = $item->is_covered ? 0 : $subtotal;

            DB::table('insurance_claim_items')
                ->where('id', $item->id)
                ->update([
                    'quantity' => $correctQty,
                    'subtotal' => $subtotal,
                    'insurance_pays' => $insurancePays,
                    'patient_pays' => $patientPays,
                    'updated_at' => now(),
                ]);

            $claimsToRecalculate->push($item->claim_id);
            $updated++;
        });

        $this->newLine(2);

        // Recalculate claim totals for affected claims
        $uniqueClaimIds = $claimsToRecalculate->unique()->values();
        if ($uniqueClaimIds->isNotEmpty()) {
            $this->info("Recalculating totals for {$uniqueClaimIds->count()} claims...");

            $this->withProgressBar($uniqueClaimIds, function ($claimId) {
                $claim = \App\Models\InsuranceClaim::with('items', 'gdrgTariff')->find($claimId);
                if (! $claim) {
                    return;
                }

                $isNhis = $claim->isNhisClaim();
                $gdrgAmount = (float) ($claim->gdrgTariff?->tariff_price ?? $claim->gdrg_amount ?? 0);

                if ($isNhis) {
                    $itemsTotal = $claim->items
                        ->whereNotNull('nhis_price')
                        ->sum(fn ($item) => (float) $item->nhis_price * (int) $item->quantity);
                } else {
                    $itemsTotal = $claim->items->sum('subtotal');
                }

                $claim->update([
                    'total_claim_amount' => $gdrgAmount + $itemsTotal,
                    'insurance_covered_amount' => $gdrgAmount + $itemsTotal,
                ]);
            });

            $this->newLine(2);
        }

        $this->info("Done. Updated: {$updated}, Skipped: {$skipped}");

        // Save report of skipped items
        if ($skippedItems->isNotEmpty()) {
            $reportPath = storage_path('logs/claim-backfill-skipped-'.now()->format('Y-m-d_His').'.json');
            file_put_contents($reportPath, json_encode([
                'summary' => [
                    'total_processed' => $items->count(),
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'timestamp' => now()->toDateTimeString(),
                ],
                'skipped_items' => $skippedItems->toArray(),
            ], JSON_PRETTY_PRINT));

            $this->newLine();
            $this->info("Skipped items report saved to: {$reportPath}");
        }

        return self::SUCCESS;
    }
}
