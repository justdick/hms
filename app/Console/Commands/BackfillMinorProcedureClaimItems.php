<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimItem;
use App\Services\InsuranceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillMinorProcedureClaimItems extends Command
{
    protected $signature = 'claims:backfill-minor-procedures {--dry-run : Show what would be done without making changes}';

    protected $description = 'Backfill missing minor procedure charges and claim items for insured patients';

    public function handle(InsuranceService $insuranceService): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN - No changes will be made.');
        }

        // Find claims with completed minor procedures but no procedure claim items
        $affectedClaims = DB::select("
            SELECT DISTINCT ic.id as claim_id, ic.claim_check_code, ic.patient_checkin_id,
                   ic.patient_insurance_id, ic.status as claim_status,
                   mp.id as minor_procedure_id, mp.minor_procedure_type_id, mp.performed_at,
                   mpt.name as procedure_name, mpt.code as procedure_code, mpt.price as procedure_price,
                   pc.checked_in_at
            FROM insurance_claims ic
            JOIN patient_checkins pc ON pc.id = ic.patient_checkin_id
            JOIN minor_procedures mp ON mp.patient_checkin_id = pc.id AND mp.status = 'completed'
            LEFT JOIN minor_procedure_types mpt ON mpt.id = mp.minor_procedure_type_id
            LEFT JOIN insurance_claim_items ici ON ici.insurance_claim_id = ic.id AND ici.item_type = 'procedure'
            WHERE ici.id IS NULL AND ic.deleted_at IS NULL
            ORDER BY ic.id
        ");

        if (empty($affectedClaims)) {
            $this->info('No affected claims found. Nothing to backfill.');

            return self::SUCCESS;
        }

        $this->info('Found '.count($affectedClaims).' claims needing backfill.');
        $this->newLine();

        $created = 0;
        $errors = 0;

        foreach ($affectedClaims as $row) {
            $this->line("Claim #{$row->claim_id} ({$row->claim_check_code}) - {$row->procedure_name} ({$row->procedure_code}) - Price: {$row->procedure_price}");

            if ($dryRun) {
                $this->line("  → Would create charge + claim item for minor procedure #{$row->minor_procedure_id}");
                $created++;

                continue;
            }

            try {
                DB::transaction(function () use ($row, $insuranceService, &$created) {
                    $claim = InsuranceClaim::find($row->claim_id);
                    $patientInsurance = $claim->patientInsurance;

                    if (! $patientInsurance) {
                        $this->warn("  → Skipping: No patient insurance found for claim #{$row->claim_id}");

                        return;
                    }

                    // Check if a charge already exists for this checkin + minor_procedure
                    $existingCharge = Charge::where('patient_checkin_id', $row->patient_checkin_id)
                        ->where('service_type', 'minor_procedure')
                        ->whereJsonContains('metadata->minor_procedure_id', $row->minor_procedure_id)
                        ->first();

                    if (! $existingCharge) {
                        // Also check by matching metadata minor_procedure_id as integer
                        $existingCharge = Charge::where('patient_checkin_id', $row->patient_checkin_id)
                            ->where('service_type', 'minor_procedure')
                            ->first();

                        // If there's a charge but for a different procedure, don't reuse it
                        if ($existingCharge && ($existingCharge->metadata['minor_procedure_id'] ?? null) != $row->minor_procedure_id) {
                            $existingCharge = null;
                        }
                    }

                    // Calculate coverage
                    $coverage = $insuranceService->calculateCoverage(
                        $patientInsurance,
                        'procedure',
                        $row->procedure_code,
                        (float) $row->procedure_price,
                        1,
                        null,
                        $row->minor_procedure_type_id
                    );

                    if ($existingCharge) {
                        // Update existing charge to link to claim
                        $existingCharge->updateQuietly([
                            'insurance_claim_id' => $claim->id,
                            'is_insurance_claim' => true,
                            'insurance_tariff_amount' => $coverage['insurance_tariff'],
                            'insurance_covered_amount' => $coverage['insurance_pays'],
                            'patient_copay_amount' => $coverage['patient_pays'],
                            'amount' => $coverage['insurance_tariff'] > 0 ? $coverage['insurance_tariff'] : $existingCharge->amount,
                        ]);
                        $chargeId = $existingCharge->id;
                        $this->line("  → Updated existing charge #{$chargeId}");
                    } else {
                        // Create new charge (without triggering observer to avoid double claim item)
                        $charge = new Charge;
                        $charge->patient_checkin_id = $row->patient_checkin_id;
                        $charge->service_type = 'minor_procedure';
                        $charge->service_code = $row->procedure_code;
                        $charge->description = "Minor Procedure: {$row->procedure_name}";
                        $charge->amount = $coverage['insurance_tariff'] > 0 ? $coverage['insurance_tariff'] : $row->procedure_price;
                        $charge->charge_type = 'minor_procedure';
                        $charge->status = 'pending';
                        $charge->charged_at = $row->performed_at ?? now();
                        $charge->is_insurance_claim = true;
                        $charge->insurance_claim_id = $claim->id;
                        $charge->insurance_tariff_amount = $coverage['insurance_tariff'];
                        $charge->insurance_covered_amount = $coverage['insurance_pays'];
                        $charge->patient_copay_amount = $coverage['patient_pays'];
                        $charge->metadata = [
                            'minor_procedure_id' => $row->minor_procedure_id,
                            'minor_procedure_type_id' => $row->minor_procedure_type_id,
                            'procedure_type_code' => $row->procedure_code,
                            'procedure_type_name' => $row->procedure_name,
                            'backfilled' => true,
                        ];
                        $charge->created_by_type = 'system';
                        $charge->created_by_id = 0;
                        $charge->saveQuietly();
                        $chargeId = $charge->id;
                        $this->line("  → Created charge #{$chargeId}");
                    }

                    // Create claim item
                    $claimItem = InsuranceClaimItem::create([
                        'insurance_claim_id' => $claim->id,
                        'charge_id' => $chargeId,
                        'item_date' => $row->checked_in_at ? date('Y-m-d', strtotime($row->checked_in_at)) : now()->toDateString(),
                        'item_type' => 'procedure',
                        'code' => $row->procedure_code,
                        'description' => "Minor Procedure: {$row->procedure_name}",
                        'quantity' => 1,
                        'unit_tariff' => $coverage['insurance_tariff'],
                        'subtotal' => $coverage['subtotal'],
                        'is_covered' => $coverage['is_covered'],
                        'coverage_percentage' => $coverage['coverage_percentage'],
                        'insurance_pays' => $coverage['insurance_pays'],
                        'patient_pays' => $coverage['patient_pays'],
                        'is_approved' => null,
                        'is_unmapped' => $coverage['is_unmapped'] ?? false,
                        'has_flexible_copay' => $coverage['has_flexible_copay'] ?? false,
                    ]);

                    // Link charge to claim item
                    Charge::where('id', $chargeId)->update(['insurance_claim_item_id' => $claimItem->id]);

                    // Update claim totals
                    $claim->increment('total_claim_amount', $coverage['subtotal']);
                    $claim->increment('insurance_covered_amount', $coverage['insurance_pays']);
                    $claim->increment('patient_copay_amount', $coverage['patient_pays']);

                    $this->line("  → Created claim item #{$claimItem->id} (tariff: {$coverage['insurance_tariff']}, insurance pays: {$coverage['insurance_pays']}, patient pays: {$coverage['patient_pays']})");
                    $created++;
                });
            } catch (\Exception $e) {
                $this->error("  → Error: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Done. Created/updated: {$created}, Errors: {$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
