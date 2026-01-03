<?php

namespace App\Console\Commands\Migration;

use App\Models\PatientAdmission;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixCurrentlyAdmittedPatients extends Command
{
    protected $signature = 'migrate:fix-currently-admitted 
                            {--dry-run : Run without actually updating data}
                            {--discharge-date= : Custom discharge date (defaults to today)}';

    protected $description = 'Auto-discharge currently admitted patients from Mittag migration (patients who were admitted at time of migration)';

    private int $updated = 0;

    private int $notFound = 0;

    private int $alreadyDischarged = 0;

    public function handle(): int
    {
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║     AUTO-DISCHARGE MITTAG CURRENTLY ADMITTED PATIENTS        ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $this->info('This command will discharge patients who were admitted in Mittag');
        $this->info('at the time of migration. Their full history is preserved.');
        $this->newLine();

        try {
            DB::connection('mittag_old')->getPdo();
            $this->info('✓ Connected to mittag_old database');
        } catch (\Exception $e) {
            $this->error('Cannot connect to mittag_old database: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Get discharge date
        $dischargeDate = $this->option('discharge-date')
            ? Carbon::parse($this->option('discharge-date'))
            : Carbon::now();

        $this->info("Discharge date: {$dischargeDate->format('Y-m-d')}");

        // Get all admissions from Mittag that have no discharge date
        $currentlyAdmitted = DB::connection('mittag_old')
            ->table('ipd_register')
            ->where(function ($query) {
                $query->where('date_discharged', '0000-00-00')
                    ->orWhereNull('date_discharged');
            })
            ->get();

        $total = $currentlyAdmitted->count();
        $this->info("Found {$total} currently admitted patients in Mittag");

        if ($total === 0) {
            $this->info('No currently admitted patients to process.');

            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No data will be updated');
        }

        // Show breakdown by ward
        $byWard = DB::connection('mittag_old')
            ->table('ipd_register as ir')
            ->leftJoin('wards as w', 'ir.ward', '=', 'w.id')
            ->where(function ($query) {
                $query->where('ir.date_discharged', '0000-00-00')
                    ->orWhereNull('ir.date_discharged');
            })
            ->select('w.name as ward_name', DB::raw('COUNT(*) as count'))
            ->groupBy('ir.ward', 'w.name')
            ->get();

        $this->newLine();
        $this->info('Breakdown by ward:');
        foreach ($byWard as $ward) {
            $wardName = $ward->ward_name ?: 'Unknown Ward';
            $this->line("  • {$wardName}: {$ward->count}");
        }
        $this->newLine();

        $this->warn('These patients will be discharged with:');
        $this->line('  • Status: discharged');
        $this->line("  • Discharge date: {$dischargeDate->format('Y-m-d H:i:s')}");
        $this->line("  • Discharge notes: 'System Migration - Patient was admitted at time of HMS migration. Admission continued in legacy system.'");
        $this->newLine();

        if (! $this->confirm('Do you want to auto-discharge these patients?')) {
            $this->info('Operation cancelled.');

            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($currentlyAdmitted as $old) {
            $this->dischargeAdmission($old, $dischargeDate);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Auto-discharge completed:');
        $this->line("  ✓ Discharged: {$this->updated}");
        $this->line("  ⊘ Already discharged: {$this->alreadyDischarged}");
        $this->line("  ✗ Not found in HMS: {$this->notFound}");

        return Command::SUCCESS;
    }

    private function dischargeAdmission(object $old, Carbon $dischargeDate): void
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

        // Check if already discharged
        $admission = PatientAdmission::find($migrationLog->new_id);
        if (! $admission) {
            $this->notFound++;

            return;
        }

        if ($admission->status === 'discharged' && $admission->discharged_at) {
            $this->alreadyDischarged++;

            return;
        }

        if ($this->option('dry-run')) {
            $this->updated++;

            return;
        }

        // Auto-discharge the admission
        $admission->update([
            'status' => 'discharged',
            'discharged_at' => $dischargeDate,
            'discharge_notes' => 'System Migration - Patient was admitted at time of HMS migration. Admission continued in legacy system.',
            'discharged_by_id' => 1, // System/admin user
        ]);

        $this->updated++;
    }
}
