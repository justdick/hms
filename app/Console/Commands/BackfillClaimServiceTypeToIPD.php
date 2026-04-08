<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillClaimServiceTypeToIPD extends Command
{
    protected $signature = 'backfill:claim-service-type-ipd
                            {--from=2026-03-01 : Start date (YYYY-MM-DD)}
                            {--to= : End date (YYYY-MM-DD), defaults to today}
                            {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Convert OPD claims to IPD for patients who have admission records (admitted or discharged)';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        try {
            $startDate = \Carbon\Carbon::parse($this->option('from'))->startOfDay();
            $endDate = $this->option('to')
                ? \Carbon\Carbon::parse($this->option('to'))->endOfDay()
                : now()->endOfDay();
        } catch (\Throwable $e) {
            $this->error('Invalid date format. Use YYYY-MM-DD (e.g. 2026-03-01).');

            return self::FAILURE;
        }

        if ($isDryRun) {
            $this->info('DRY RUN — no changes will be made.');
        }

        $this->info("Scanning claims from {$startDate->toDateString()} to {$endDate->toDateString()}...");

        // Find OPD claims where the patient has an admission record
        // Path: admission -> consultation -> check-in -> claim
        // This is the most reliable signal — the admission record proves it's IPD
        $claimIds = DB::table('insurance_claims as ic')
            ->join('patient_checkins as pc', 'ic.patient_checkin_id', '=', 'pc.id')
            ->join('consultations as c', 'c.patient_checkin_id', '=', 'pc.id')
            ->join('patient_admissions as pa', 'pa.consultation_id', '=', 'c.id')
            ->where('ic.date_of_attendance', '>=', $startDate)
            ->where('ic.date_of_attendance', '<=', $endDate)
            ->where('ic.type_of_service', 'OPD')
            ->whereNull('ic.deleted_at')
            ->where('pa.migrated_from_mittag', false)
            ->pluck('ic.id');

        $this->info("Found {$claimIds->count()} OPD claims linked to admissions that should be IPD.");

        if ($claimIds->isEmpty()) {
            $this->info('Nothing to update.');

            return self::SUCCESS;
        }

        // Show sample
        $sample = DB::table('insurance_claims as ic')
            ->join('patient_checkins as pc', 'ic.patient_checkin_id', '=', 'pc.id')
            ->join('consultations as c', 'c.patient_checkin_id', '=', 'pc.id')
            ->join('patient_admissions as pa', 'pa.consultation_id', '=', 'c.id')
            ->whereIn('ic.id', $claimIds->take(10))
            ->select(
                'ic.id',
                'ic.claim_check_code',
                'ic.patient_surname',
                'ic.patient_other_names',
                'ic.date_of_attendance',
                'pa.admission_number',
                'pa.status as admission_status'
            )
            ->get();

        $this->table(
            ['Claim ID', 'Claim Code', 'Patient', 'Date', 'Admission #', 'Admission Status'],
            $sample->map(fn ($row) => [
                $row->id,
                $row->claim_check_code,
                "{$row->patient_surname} {$row->patient_other_names}",
                $row->date_of_attendance,
                $row->admission_number,
                $row->admission_status,
            ])->toArray()
        );

        if ($claimIds->count() > 10) {
            $this->info('... and '.($claimIds->count() - 10).' more.');
        }

        if ($isDryRun) {
            return self::SUCCESS;
        }

        if (! $this->confirm("Update {$claimIds->count()} claims from OPD to IPD?")) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $updated = DB::table('insurance_claims')
            ->whereIn('id', $claimIds)
            ->update(['type_of_service' => 'IPD']);

        $this->info("Updated {$updated} claims from OPD to IPD.");

        return self::SUCCESS;
    }
}
