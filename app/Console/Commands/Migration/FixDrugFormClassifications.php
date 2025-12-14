<?php

namespace App\Console\Commands\Migration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixDrugFormClassifications extends Command
{
    protected $signature = 'migrate:fix-drug-forms 
                            {--dry-run : Show what would be fixed without making changes}';

    protected $description = 'Fix drugs with incorrect form classifications (injections/drops marked as syrup)';

    public function handle(): int
    {
        $this->info('Checking for drugs with incorrect form classifications...');
        $this->newLine();

        // Find drugs where name suggests a different form than what's set
        $misclassified = DB::table('drugs')
            ->where(function ($query) {
                $query->where('name', 'like', '%injection%')
                    ->where('form', '!=', 'injection');
            })
            ->orWhere(function ($query) {
                $query->where('name', 'like', '%drop%')
                    ->where('form', '!=', 'drops');
            })
            ->get();

        if ($misclassified->isEmpty()) {
            $this->info('✓ No misclassified drugs found.');

            return Command::SUCCESS;
        }

        $this->warn("Found {$misclassified->count()} drugs with incorrect form classifications:");
        $this->newLine();

        $fixes = [];

        foreach ($misclassified as $drug) {
            $nameLower = strtolower($drug->name);
            $newForm = $drug->form;
            $newUnitType = $drug->unit_type;

            if (str_contains($nameLower, 'injection')) {
                $newForm = 'injection';
                $newUnitType = 'vial';
            } elseif (str_contains($nameLower, 'drop')) {
                $newForm = 'drops';
                $newUnitType = 'bottle'; // drops still come in bottles
            }

            if ($newForm !== $drug->form || $newUnitType !== $drug->unit_type) {
                $fixes[] = [
                    'id' => $drug->id,
                    'name' => $drug->name,
                    'old_form' => $drug->form,
                    'new_form' => $newForm,
                    'old_unit_type' => $drug->unit_type,
                    'new_unit_type' => $newUnitType,
                ];

                $this->line("  ID {$drug->id}: {$drug->name}");
                $this->line("    Form: {$drug->form} → {$newForm}");
                if ($newUnitType !== $drug->unit_type) {
                    $this->line("    Unit Type: {$drug->unit_type} → {$newUnitType}");
                }
                $this->newLine();
            }
        }

        if (empty($fixes)) {
            $this->info('✓ No fixes needed.');

            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run - no changes made.');

            return Command::SUCCESS;
        }

        if (! $this->confirm('Apply these fixes?', true)) {
            $this->info('Cancelled.');

            return Command::SUCCESS;
        }

        // Apply fixes
        $fixed = 0;
        foreach ($fixes as $fix) {
            DB::table('drugs')
                ->where('id', $fix['id'])
                ->update([
                    'form' => $fix['new_form'],
                    'unit_type' => $fix['new_unit_type'],
                ]);
            $fixed++;
        }

        $this->newLine();
        $this->info("✓ Fixed {$fixed} drug(s).");

        return Command::SUCCESS;
    }
}
