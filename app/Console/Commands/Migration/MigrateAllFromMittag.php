<?php

namespace App\Console\Commands\Migration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateAllFromMittag extends Command
{
    protected $signature = 'migrate:all-from-mittag 
                            {--dry-run : Run without actually inserting data}
                            {--skip-existing : Skip records that already exist}
                            {--only= : Only run specific migration (patients,drugs)}';

    protected $description = 'Run all Mittag migrations in the correct order';

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

        if (!$this->confirm('Do you want to proceed with the migration?')) {
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
        $migrations = [
            'patients' => 'migrate:patients-from-mittag',
            'drugs' => 'migrate:drugs-from-mittag',
        ];

        if ($only) {
            $selected = explode(',', $only);
            $migrations = array_filter($migrations, fn($key) => in_array($key, $selected), ARRAY_FILTER_USE_KEY);
        }

        foreach ($migrations as $name => $command) {
            $this->newLine();
            $this->info("═══ Migrating {$name} ═══");
            $this->call($command, $options);
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
            'patients' => 'Patients',
            'drugs' => 'Drugs',
            'checkin' => 'Check-ins',
            'opd_consultation' => 'Consultations',
            'sponsors' => 'Insurance Sponsors',
        ];

        foreach ($tables as $table => $label) {
            try {
                $count = DB::connection('mittag_old')->table($table)->count();
                $this->line("  • {$label}: {$count}");
            } catch (\Exception $e) {
                $this->line("  • {$label}: (table not found)");
            }
        }
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
