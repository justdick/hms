<?php

namespace App\Console\Commands\Migration;

use App\Models\Diagnosis;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateDiagnosesFromMittag extends Command
{
    protected $signature = 'migrate:diagnoses-from-mittag 
                            {--dry-run : Run without actually inserting data}';

    protected $description = 'Migrate ICD-10 diagnoses from Mittag to HMS';

    private int $created = 0;

    private int $updated = 0;

    private int $skipped = 0;

    public function handle(): int
    {
        $this->info('Starting diagnosis migration from Mittag...');

        try {
            DB::connection('mittag_old')->getPdo();
            $this->info('✓ Connected to mittag_old database');
        } catch (\Exception $e) {
            $this->error('Cannot connect to mittag_old database: '.$e->getMessage());

            return Command::FAILURE;
        }

        $total = DB::connection('mittag_old')->table('tb_data_icd')->count();
        $this->info("Found {$total} ICD-10 codes in Mittag");

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No data will be inserted');
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        DB::connection('mittag_old')
            ->table('tb_data_icd')
            ->orderBy('code')
            ->chunk(500, function ($diagnoses) use ($bar) {
                foreach ($diagnoses as $old) {
                    $this->migrateDiagnosis($old);
                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->info('Migration completed:');
        $this->line("  ✓ Created: {$this->created}");
        $this->line("  ↻ Updated: {$this->updated}");
        $this->line("  ⊘ Skipped: {$this->skipped}");

        return Command::SUCCESS;
    }

    private function migrateDiagnosis(object $old): void
    {
        if (empty($old->code) || empty($old->diagnosis)) {
            $this->skipped++;

            return;
        }

        if ($this->option('dry-run')) {
            $this->created++;

            return;
        }

        $diagnosis = Diagnosis::updateOrCreate(
            ['icd_10' => trim($old->code)],
            [
                'diagnosis' => trim($old->diagnosis),
                'code' => 'ICD-'.trim($old->code),
                'g_drg' => trim($old->diagnosis),
            ]
        );

        $diagnosis->wasRecentlyCreated ? $this->created++ : $this->updated++;
    }
}
