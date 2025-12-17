<?php

namespace App\Console\Commands\Migration;

use App\Models\Drug;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RestoreDrugNamesFromMittag extends Command
{
    protected $signature = 'migrate:restore-drug-names 
                            {--dry-run : Show what would be fixed without making changes}';

    protected $description = 'Restore drug names from Mittag old system (fixes truncated names from NHIS import)';

    private int $restored = 0;

    private int $skipped = 0;

    public function handle(): int
    {
        $this->info('Restoring drug names from Mittag old system...');
        $this->newLine();

        try {
            DB::connection('mittag_old')->getPdo();
            $this->info('✓ Connected to mittag_old database');
        } catch (\Exception $e) {
            $this->error('Cannot connect to mittag_old database: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Get all drugs that were migrated from mittag
        $hmsDrugs = Drug::where('migrated_from_mittag', true)->get();
        $this->info("Found {$hmsDrugs->count()} drugs migrated from Mittag");

        // Get mittag drugs indexed by code
        $mittagDrugs = DB::connection('mittag_old')
            ->table('drugs')
            ->get()
            ->keyBy('code');

        $this->newLine();

        $fixes = [];

        foreach ($hmsDrugs as $drug) {
            $mittagDrug = $mittagDrugs->get($drug->drug_code);

            if (! $mittagDrug) {
                $this->skipped++;

                continue;
            }

            // Clean the mittag name - remove tab and everything after it
            $mittagName = $mittagDrug->name;
            if (str_contains($mittagName, "\t")) {
                $mittagName = trim(explode("\t", $mittagName)[0]);
            }
            $mittagName = trim($mittagName);

            // Check if names are different
            if ($drug->name !== $mittagName) {
                $fixes[] = [
                    'id' => $drug->id,
                    'drug_code' => $drug->drug_code,
                    'current_name' => $drug->name,
                    'correct_name' => $mittagName,
                ];

                $this->line("  {$drug->drug_code}:");
                $this->line("    Current: {$drug->name}");
                $this->line("    Correct: {$mittagName}");
                $this->newLine();
            }
        }

        if (empty($fixes)) {
            $this->info('✓ All drug names are correct. No fixes needed.');

            return Command::SUCCESS;
        }

        $this->warn('Found '.count($fixes).' drugs with incorrect names.');
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('Dry run - no changes made.');

            return Command::SUCCESS;
        }

        if (! $this->confirm('Apply these fixes?', true)) {
            $this->info('Cancelled.');

            return Command::SUCCESS;
        }

        // Apply fixes
        foreach ($fixes as $fix) {
            Drug::where('id', $fix['id'])->update(['name' => $fix['correct_name']]);
            $this->restored++;
        }

        $this->newLine();
        $this->info("✓ Restored {$this->restored} drug name(s).");

        return Command::SUCCESS;
    }
}
