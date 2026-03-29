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
                            {--since=2026-02-01 : Only include prescriptions created on or after this date (defaults to Feb 2026, excluding January)}
                            {--patch-existing : Patch dose/frequency/duration on already-created pending items that are missing them}';

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

        // Patch existing pending items that are missing dose/frequency/duration
        if ($this->option('patch-existing')) {
            $this->newLine();
            $this->info('Patching existing pending items with missing dose/frequency/duration...');

            // Join via claim → checkin → consultation → prescriptions (not via charges,
            // since pending items have charge_id = NULL)
            $pendingItems = DB::table('insurance_claim_items as ici')
                ->join('insurance_claims as ic', 'ici.insurance_claim_id', '=', 'ic.id')
                ->join('patient_checkins as pc', 'ic.patient_checkin_id', '=', 'pc.id')
                ->join('consultations as con', 'con.patient_checkin_id', '=', 'pc.id')
                ->join('prescriptions as p', function ($join) {
                    $join->on('p.consultation_id', '=', 'con.id')
                        ->whereNull('p.quantity')
                        ->whereNotNull('p.drug_id');
                })
                ->join('drugs as d', function ($join) {
                    $join->on('d.id', '=', 'p.drug_id')
                        ->whereColumn('ici.code', 'd.drug_code');
                })
                ->where('ici.is_pending_quantity', true)
                ->where(function ($q) {
                    $q->whereNull('ici.dose')
                        ->orWhereNull('ici.frequency');
                })
                ->select([
                    'ici.id',
                    'p.dose_quantity',
                    'p.frequency',
                    'p.duration',
                ])
                ->get();

            if ($pendingItems->isEmpty()) {
                $this->info('No existing pending items need patching.');
            } else {
                $patched = 0;
                $this->withProgressBar($pendingItems, function ($row) use ($isDryRun, &$patched) {
                    if (! $isDryRun) {
                        DB::table('insurance_claim_items')->where('id', $row->id)->update([
                            'dose' => $row->dose_quantity,
                            'frequency' => $row->frequency,
                            'duration' => $row->duration,
                            'updated_at' => now(),
                        ]);
                    }
                    $patched++;
                });
                $this->newLine(2);
                $this->info($isDryRun ? "Would patch {$patched} existing pending item(s)." : "Patched {$patched} existing pending item(s).");
            }
        }

        return self::SUCCESS;
    }
}
