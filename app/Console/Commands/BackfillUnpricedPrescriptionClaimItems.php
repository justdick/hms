<?php

namespace App\Console\Commands;

use App\Models\InsuranceClaim;
use App\Models\Prescription;
use App\Services\InsuranceClaimService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillUnpricedPrescriptionClaimItems extends Command
{
    protected $signature = 'claims:backfill-unpriced-prescriptions
                            {--from=2026-02-01 : Start date (inclusive)}
                            {--to= : End date (inclusive, defaults to today)}
                            {--dry-run : Show what would be done without making changes}';

    protected $description = 'Backfill missing insurance claim items for unpriced prescriptions';

    public function handle(InsuranceClaimService $claimService): int
    {
        $from = $this->option('from');
        $to = $this->option('to') ?: now()->toDateString();
        $dryRun = $this->option('dry-run');

        $this->info("Finding missing unpriced prescription claim items from {$from} to {$to}...");

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be made.');
        }

        // Find unpriced prescriptions that have an insurance claim but no claim item
        $missing = DB::table('prescriptions as p')
            ->join('consultations as c', 'c.id', '=', 'p.consultation_id')
            ->join('insurance_claims as ic', 'ic.patient_checkin_id', '=', 'c.patient_checkin_id')
            ->join('drugs as d', 'd.id', '=', 'p.drug_id')
            ->whereRaw('NOT EXISTS (
                SELECT 1 FROM insurance_claim_items ici
                WHERE ici.insurance_claim_id = ic.id
                AND ici.code = d.drug_code
                AND ici.item_type = ?
            )', ['drug'])
            ->where('p.is_unpriced', true)
            ->whereNotNull('p.drug_id')
            ->whereNotNull('p.quantity')
            ->where('p.created_at', '>=', $from)
            ->where('p.created_at', '<', date('Y-m-d', strtotime($to.' +1 day')))
            ->select('p.id as prescription_id', 'ic.id as claim_id')
            ->get();

        if ($missing->isEmpty()) {
            $this->info('No missing claim items found.');

            return self::SUCCESS;
        }

        $this->info("Found {$missing->count()} missing claim items across ".$missing->pluck('claim_id')->unique()->count().' claims.');

        if ($dryRun) {
            $this->table(
                ['Prescription ID', 'Claim ID'],
                $missing->map(fn ($row) => [$row->prescription_id, $row->claim_id])->take(20)->toArray()
            );

            if ($missing->count() > 20) {
                $this->info('... and '.($missing->count() - 20).' more.');
            }

            return self::SUCCESS;
        }

        if (! $this->confirm("Proceed to add {$missing->count()} claim items?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($missing->count());
        $added = 0;
        $errors = 0;

        foreach ($missing as $row) {
            try {
                $prescription = Prescription::with('drug')->find($row->prescription_id);
                $claim = InsuranceClaim::find($row->claim_id);

                if (! $prescription || ! $claim || ! $prescription->drug) {
                    $errors++;
                    $bar->advance();

                    continue;
                }

                $claimService->addPrescriptionToClaimDirectly($claim, $prescription);
                $added++;
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Error for prescription {$row->prescription_id}: {$e->getMessage()}");
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Added: {$added}, Errors: {$errors}");

        return self::SUCCESS;
    }
}
