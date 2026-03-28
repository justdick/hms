<?php

namespace App\Console\Commands;

use App\Models\InsuranceClaim;
use App\Models\Prescription;
use App\Services\InsuranceClaimService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillInjectablePendingClaimItems extends Command
{
    protected $signature = 'claims:backfill-injectable-pending
                            {--dry-run : Show what would be created without making changes}
                            {--claim-status= : Only target prescriptions on claims with this status (e.g. draft, pending_vetting)}
                            {--since=2026-02-01 : Only include prescriptions created on or after this date (defaults to Feb 2026, excluding January)}';

    protected $description = 'Create pending-quantity claim items for historical injectable/infusion prescriptions that have no claim item yet';

    public function __construct(protected InsuranceClaimService $claimService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $claimStatus = $this->option('claim-status');
        $since = $this->option('since');

        if ($isDryRun) {
            $this->info('DRY RUN — no changes will be made.');
        }

        $this->info("Filtering prescriptions created since: {$since}");

        // Find injectable prescriptions (quantity IS NULL, drug_id set, status = prescribed)
        // that belong to insured patients with an active claim but have no claim item for that drug code.
        $prescriptions = Prescription::query()
            ->whereNull('quantity')
            ->whereNotNull('drug_id')
            ->where('status', 'prescribed')
            ->where('created_at', '>=', $since)
            ->whereHas('drug', fn ($q) => $q->whereNotNull('drug_code')->where('drug_code', '!=', ''))
            ->with(['drug', 'consultation'])
            ->whereHas('consultation', function ($q) use ($claimStatus) {
                $q->whereHas('patientCheckin', function ($cq) use ($claimStatus) {
                    $cq->whereHas('insuranceClaim', function ($iq) use ($claimStatus) {
                        if ($claimStatus) {
                            $iq->where('status', $claimStatus);
                        }
                    });
                });
            })
            ->get();

        if ($prescriptions->isEmpty()) {
            $this->info('No injectable prescriptions found needing backfill.');

            return self::SUCCESS;
        }

        $this->info("Found {$prescriptions->count()} injectable prescription(s) to process.");

        $created = 0;
        $skipped = 0;
        $skippedReasons = collect();

        $this->withProgressBar($prescriptions, function (Prescription $prescription) use ($isDryRun, &$created, &$skipped, &$skippedReasons) {
            $drug = $prescription->drug;

            if (! $drug || ! $drug->drug_code) {
                $skipped++;
                $skippedReasons->push([
                    'prescription_id' => $prescription->id,
                    'reason' => 'Drug missing or no drug_code',
                ]);

                return;
            }

            $checkinId = $prescription->consultation?->patient_checkin_id;

            if (! $checkinId) {
                $skipped++;
                $skippedReasons->push([
                    'prescription_id' => $prescription->id,
                    'reason' => 'No consultation or checkin found',
                ]);

                return;
            }

            $claim = InsuranceClaim::where('patient_checkin_id', $checkinId)->first();

            if (! $claim) {
                $skipped++;
                $skippedReasons->push([
                    'prescription_id' => $prescription->id,
                    'reason' => 'No insurance claim for checkin',
                ]);

                return;
            }

            // Check if a claim item already exists for this drug code on this claim
            $existingItem = $claim->items()
                ->where('code', $drug->drug_code)
                ->where('item_type', 'drug')
                ->exists();

            if ($existingItem) {
                $skipped++;
                $skippedReasons->push([
                    'prescription_id' => $prescription->id,
                    'drug_code' => $drug->drug_code,
                    'claim_id' => $claim->id,
                    'reason' => 'Claim item already exists for this drug',
                ]);

                return;
            }

            if ($isDryRun) {
                $created++;

                return;
            }

            DB::transaction(function () use ($claim, $prescription) {
                $this->claimService->createPendingQuantityClaimItem($claim, $prescription);
            });

            $created++;
        });

        $this->newLine(2);
        $this->info("Done. Created: {$created}, Skipped: {$skipped}");

        if ($skippedReasons->isNotEmpty()) {
            $reportPath = storage_path('logs/injectable-backfill-skipped-'.now()->format('Y-m-d_His').'.json');
            file_put_contents($reportPath, json_encode([
                'summary' => [
                    'total_processed' => $prescriptions->count(),
                    'created' => $created,
                    'skipped' => $skipped,
                    'timestamp' => now()->toDateTimeString(),
                ],
                'skipped_items' => $skippedReasons->toArray(),
            ], JSON_PRETTY_PRINT));

            $this->newLine();
            $this->info("Skipped items report saved to: {$reportPath}");
        }

        if ($isDryRun && $created > 0) {
            $this->newLine();
            $this->line("Run without --dry-run to create {$created} pending claim item(s).");
        }

        return self::SUCCESS;
    }
}
