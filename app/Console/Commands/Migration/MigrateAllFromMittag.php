<?php

namespace App\Console\Commands\Migration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateAllFromMittag extends Command
{
    protected $signature = 'migrate:all-from-mittag 
                            {--dry-run : Run without actually inserting data}
                            {--skip-existing : Skip records that already exist}
                            {--only= : Only run specific migration (patients,checkins,etc)}
                            {--from= : Start from a specific migration step}';

    protected $description = 'Run all Mittag migrations in the correct order';

    // Disable memory limit and set unlimited execution time for long migrations
    public function __construct()
    {
        parent::__construct();
        ini_set('memory_limit', '1G');
        set_time_limit(0);
    }

    public function handle(): int
    {
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║           MITTAG TO HMS MIGRATION TOOL                       ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        // Check connection first
        try {
            DB::connection('mittag_old')->getPdo();
            $this->info('✓ Connected to mittag_old database');
        } catch (\Exception $e) {
            $this->error('✗ Cannot connect to mittag_old database');
            $this->newLine();
            $this->warn('Setup Instructions:');
            $this->line('1. Create the database:');
            $this->line('   mysql -u root -e "CREATE DATABASE mittag_old;"');
            $this->newLine();
            $this->line('2. Import the backup:');
            $this->line('   mysql -u root mittag_old < backup_mittag.sql');
            $this->newLine();
            $this->line('3. Add to your .env file (if different credentials):');
            $this->line('   MITTAG_DB_DATABASE=mittag_old');
            $this->line('   MITTAG_DB_USERNAME=root');
            $this->line('   MITTAG_DB_PASSWORD=');
            $this->newLine();
            $this->line('4. Run the migration table:');
            $this->line('   php artisan migrate');

            return Command::FAILURE;
        }

        // Show stats
        $this->showSourceStats();

        if (! $this->confirm('Do you want to proceed with the migration?')) {
            $this->info('Migration cancelled.');

            return Command::SUCCESS;
        }

        $options = [];
        if ($this->option('dry-run')) {
            $options['--dry-run'] = true;
        }
        if ($this->option('skip-existing')) {
            $options['--skip-existing'] = true;
        }

        $only = $this->option('only');
        $from = $this->option('from');

        // Complete migration order - dependencies matter!
        $migrations = [
            '1_diagnoses' => 'migrate:diagnoses-from-mittag',
            '2_patients' => 'migrate:patients-from-mittag',
            '3_drugs' => 'migrate:drugs-from-mittag',
            '3b_fix_drug_forms' => 'migrate:fix-drug-forms',
            '4_checkins' => 'migrate:checkins-from-mittag',
            '5_opd_vitals' => 'migrate:vitals-from-mittag --source=opd',
            '6_consultations' => 'migrate:consultations-from-mittag',
            '7_prescriptions' => 'migrate:prescriptions-from-mittag',
            '8_lab_services' => 'migrate:lab-services-from-mittag',
            '9_lab_parameters' => 'migrate:lab-parameters-from-mittag',
            '10_lab_orders' => 'migrate:lab-orders-from-mittag',
            '10b_imaging' => 'migrate:imaging-from-mittag',
            '11_wards' => 'migrate:wards-from-mittag',
            '12_admissions' => 'migrate:admissions-from-mittag',
            '13_ipd_vitals' => 'migrate:vitals-from-mittag --source=ipd',
            '14_ward_rounds' => 'migrate:ward-rounds-from-mittag',
            '15_nursing_notes' => 'migrate:nursing-notes-from-mittag',
            '16_patient_insurance' => 'migrate:patient-insurance-from-mittag',
            '17_pricing' => 'migrate:import-mittag-pricing',
        ];

        // Filter by --only option
        if ($only) {
            $selected = explode(',', $only);
            $migrations = array_filter($migrations, function ($key) use ($selected) {
                $name = preg_replace('/^\d+_/', '', $key); // Remove number prefix

                return in_array($name, $selected);
            }, ARRAY_FILTER_USE_KEY);
        }

        // Filter by --from option (start from specific step)
        if ($from) {
            $found = false;
            $migrations = array_filter($migrations, function ($key) use ($from, &$found) {
                $name = preg_replace('/^\d+_/', '', $key);
                if ($name === $from) {
                    $found = true;
                }

                return $found;
            }, ARRAY_FILTER_USE_KEY);
        }

        $total = count($migrations);
        $current = 0;

        foreach ($migrations as $name => $command) {
            $current++;
            $displayName = preg_replace('/^\d+_/', '', $name);
            $this->newLine();
            $this->info("═══ [{$current}/{$total}] Migrating {$displayName} ═══");

            // Parse command and options
            $parts = explode(' ', $command);
            $cmd = array_shift($parts);
            $cmdOptions = $options;

            foreach ($parts as $part) {
                if (str_starts_with($part, '--')) {
                    $opt = explode('=', ltrim($part, '-'), 2);
                    $cmdOptions['--'.$opt[0]] = $opt[1] ?? true;
                }
            }

            $this->call($cmd, $cmdOptions);

            // Clear memory between migrations
            gc_collect_cycles();
        }

        $this->newLine();
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║           MIGRATION COMPLETE                                 ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');

        $this->showMigrationSummary();

        return Command::SUCCESS;
    }

    private function showSourceStats(): void
    {
        $this->newLine();
        $this->info('Source Database Statistics:');

        $tables = [
            'tb_data_icd' => 'ICD-10 Diagnoses',
            'patients' => 'Patients',
            'drugs' => 'Drugs',
            'checkin' => 'Check-ins',
            'opd_vitals' => 'OPD Vitals',
            'opd_consultation' => 'Consultations (+ prescriptions)',
            'gdrg_lab' => 'Lab Services',
            'lab_param_list' => 'Lab Parameters',
            'lab_daily_register' => 'Lab Orders',
            'lab_results' => 'Lab Results',
            'img_daily_register' => 'Imaging Orders',
            'img_results' => 'Imaging Results',
            'img_comments' => 'Imaging Reports',
            'wards' => 'Wards',
            'ipd_register' => 'Admissions',
            'ipd_vitals' => 'IPD Vitals',
            'ipd_review' => 'Ward Rounds',
            'nurses_notes' => 'Nursing Notes',
            'sponsors' => 'Insurance Sponsors',
        ];

        $totalRecords = 0;
        foreach ($tables as $table => $label) {
            try {
                $count = DB::connection('mittag_old')->table($table)->count();
                $this->line("  • {$label}: ".number_format($count));
                $totalRecords += $count;
            } catch (\Exception $e) {
                $this->line("  • {$label}: (table not found)");
            }
        }
        $this->newLine();
        $this->info('Total records to process: '.number_format($totalRecords));
        $this->warn('⚠ This migration may take 30-60 minutes depending on server performance.');
        $this->newLine();
    }

    private function showMigrationSummary(): void
    {
        $this->newLine();
        $this->info('Migration Summary:');

        $stats = DB::table('mittag_migration_logs')
            ->select('entity_type', 'status', DB::raw('count(*) as count'))
            ->groupBy('entity_type', 'status')
            ->get()
            ->groupBy('entity_type');

        foreach ($stats as $entity => $statuses) {
            $this->line("  {$entity}:");
            foreach ($statuses as $stat) {
                $icon = match ($stat->status) {
                    'success' => '✓',
                    'skipped' => '⊘',
                    'failed' => '✗',
                    default => '•',
                };
                $this->line("    {$icon} {$stat->status}: {$stat->count}");
            }
        }
    }
}
