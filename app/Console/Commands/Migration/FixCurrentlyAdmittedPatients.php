<?php

namespace App\Console\Commands\Migration;

use App\Models\PatientAdmission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixCurrentlyAdmittedPatients extends Command
{
    protected $signature = 'migrate:fix-currently-admitted 
                            {--dry-run : Run without actually updating data}';

    protected $description = 'Fix currently admitted patients from Mittag migration (those with no discharge date)';

    private int $updated = 0;

    private int $notFound = 0;

    public function handle(): int
    {
        $this->info('Fixing currently admitted patients from Mittag migration...');

        try {
            DB::connection('mittag_old')->getPdo();
            $this->info('✓ Connected to mittag_old database');
        } catch (\Exception $e) {
            $this->error('Cannot connect to mittag_old database: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Get all admissions from Mittag that have no discharge date
        $currentlyAdmitted = DB::connection('mittag_old')
            ->table('ipd_register')
            ->where('date_discharged', '0000-00-00')
            ->orWhereNull('date_discharged')
            ->get();

        $total = $currentlyAdmitted->count();
        $this->info("Found {$total} currently admitted patients in Mittag");

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No data will be updated');
        }

        // Show breakdown by ward
        $byWard = DB::connection('mittag_old')
            ->table('ipd_register as ir')
            ->leftJoin('wards as w', 'ir.ward', '=', 'w.id')
            ->where('ir.date_discharged', '0000-00-00')
            ->orWhereNull('ir.date_discharged')
            ->select('w.name as ward_name', DB::raw('COUNT(*) as count'))
            ->groupBy('ir.ward', 'w.name')
            ->get();

        $this->newLine();
        $this->info('Breakdown by ward:');
        foreach ($byWard as $ward) {
            $this->line("  • {$ward->ward_name}: {$ward->count}");
        }
        $this->newLine();

        if (! $this->confirm('Do you want to update these patients to "admitted" status?')) {
            $this->info('Operation cancelled.');

            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($currentlyAdmitted as $old) {
            $this->updateAdmissionStatus($old);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Fix completed:');
        $this->line("  ✓ Updated: {$this->updated}");
        $this->line("  ✗ Not found: {$this->notFound}");

        return Command::SUCCESS;
    }

    private function updateAdmissionStatus(object $old): void
    {
        // Find the migrated admission using the migration log
        $migrationLog = DB::table('mittag_migration_logs')
            ->where('entity_type', 'admission')
            ->where('old_id', $old->id)
            ->where('status', 'success')
            ->first();

        if (! $migrationLog || ! $migrationLog->new_id) {
            $this->notFound++;

            return;
        }

        if ($this->option('dry-run')) {
            $this->updated++;

            return;
        }

        // Update the admission to 'admitted' status
        PatientAdmission::where('id', $migrationLog->new_id)
            ->update([
                'status' => 'admitted',
                'discharged_at' => null,
                'discharge_notes' => null,
                'discharged_by_id' => null,
            ]);

        $this->updated++;
    }
}
