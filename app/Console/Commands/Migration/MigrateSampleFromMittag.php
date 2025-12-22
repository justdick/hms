<?php

namespace App\Console\Commands\Migration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateSampleFromMittag extends Command
{
    protected $signature = 'migrate:sample-from-mittag 
                            {--patients=100 : Number of patients to import}
                            {--fresh : Run migrate:fresh first (WARNING: wipes database)}';

    protected $description = 'Import a small sample of data from mittag_old for development - fast import with all essential data';

    public function handle(): int
    {
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║           SAMPLE MITTAG MIGRATION (DEV)                      ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        // Check connection first
        try {
            DB::connection('mittag_old')->getPdo();
            $this->info('✓ Connected to mittag_old database');
        } catch (\Exception $e) {
            $this->error('✗ Cannot connect to mittag_old database');
            $this->newLine();
            $this->warn('Run these commands first:');
            $this->line('  mysql -u root -e "CREATE DATABASE mittag_old;"');
            $this->line('  mysql -u root mittag_old < backup_mittag.sql');

            return Command::FAILURE;
        }

        if ($this->option('fresh')) {
            if (! $this->confirm('WARNING: This will wipe your database. Continue?')) {
                return Command::FAILURE;
            }
            $this->call('migrate:fresh');
        }

        $patientLimit = (int) $this->option('patients');

        $this->info("Importing sample data (limited to {$patientLimit} patients)...");
        $this->newLine();

        // Import in correct order with limits
        $migrations = [
            ['cmd' => 'migrate:diagnoses-from-mittag', 'opts' => [], 'desc' => 'ICD-10 Diagnoses (all - needed for lookups)'],
            ['cmd' => 'migrate:patients-from-mittag', 'opts' => ['--limit' => $patientLimit], 'desc' => "Patients (limit: {$patientLimit})"],
            ['cmd' => 'migrate:drugs-from-mittag', 'opts' => [], 'desc' => 'Drugs (all - needed for prescriptions)'],
            ['cmd' => 'migrate:fix-drug-forms', 'opts' => [], 'desc' => 'Fix drug forms'],
            ['cmd' => 'migrate:checkins-from-mittag', 'opts' => ['--limit' => $patientLimit * 3], 'desc' => 'Check-ins (limit: '.($patientLimit * 3).')'],
            ['cmd' => 'migrate:vitals-from-mittag', 'opts' => ['--source' => 'opd', '--limit' => $patientLimit * 3], 'desc' => 'OPD Vitals'],
            ['cmd' => 'migrate:consultations-from-mittag', 'opts' => ['--limit' => $patientLimit * 3], 'desc' => 'Consultations'],
            ['cmd' => 'migrate:prescriptions-from-mittag', 'opts' => ['--limit' => $patientLimit * 5], 'desc' => 'Prescriptions'],
            ['cmd' => 'migrate:lab-services-from-mittag', 'opts' => [], 'desc' => 'Lab Services (all)'],
            ['cmd' => 'migrate:lab-parameters-from-mittag', 'opts' => [], 'desc' => 'Lab Parameters (all)'],
            ['cmd' => 'migrate:lab-orders-from-mittag', 'opts' => ['--limit' => $patientLimit * 2], 'desc' => 'Lab Orders'],
            ['cmd' => 'migrate:wards-from-mittag', 'opts' => [], 'desc' => 'Wards (all)'],
            ['cmd' => 'migrate:admissions-from-mittag', 'opts' => ['--limit' => $patientLimit / 2], 'desc' => 'Admissions'],
            ['cmd' => 'migrate:patient-insurance-from-mittag', 'opts' => ['--limit' => $patientLimit], 'desc' => 'Patient Insurance'],
            ['cmd' => 'migrate:import-mittag-pricing', 'opts' => [], 'desc' => 'Pricing & NHIS Tariffs (all)'],
        ];

        $total = count($migrations);
        $current = 0;

        foreach ($migrations as $migration) {
            $current++;
            $this->info("[{$current}/{$total}] {$migration['desc']}");

            try {
                $this->call($migration['cmd'], $migration['opts']);
            } catch (\Exception $e) {
                $this->warn('  ⚠ Skipped: '.$e->getMessage());
            }

            // Clear memory
            gc_collect_cycles();
        }

        $this->newLine();
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║           SAMPLE MIGRATION COMPLETE                          ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();

        $this->showSummary();

        return Command::SUCCESS;
    }

    protected function showSummary(): void
    {
        $this->info('Database Summary:');

        $tables = [
            'users' => 'Users',
            'patients' => 'Patients',
            'drugs' => 'Drugs',
            'patient_checkins' => 'Check-ins',
            'consultations' => 'Consultations',
            'prescriptions' => 'Prescriptions',
            'lab_services' => 'Lab Services',
            'lab_orders' => 'Lab Orders',
            'wards' => 'Wards',
            'patient_admissions' => 'Admissions',
            'patient_insurance' => 'Patient Insurance',
            'insurance_plans' => 'Insurance Plans',
            'nhis_tariffs' => 'NHIS Tariffs',
            'nhis_item_mappings' => 'NHIS Mappings',
        ];

        foreach ($tables as $table => $label) {
            try {
                $count = DB::table($table)->count();
                $this->line("  • {$label}: ".number_format($count));
            } catch (\Exception $e) {
                $this->line("  • {$label}: (error)");
            }
        }
    }
}
