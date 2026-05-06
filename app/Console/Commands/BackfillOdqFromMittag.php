<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillOdqFromMittag extends Command
{
    protected $signature = 'backfill:odq-from-mittag
                            {--dry-run : Show what would be updated without making changes}
                            {--limit= : Limit number of records to process}';

    protected $description = 'Backfill on_direct_questioning from Mittag odq column for migrated consultations';

    private int $updated = 0;

    private int $skipped = 0;

    private int $alreadyHasValue = 0;

    public function handle(): int
    {
        $this->info('Backfilling ODQ data from Mittag old database...');

        try {
            DB::connection('mittag_old')->getPdo();
            $this->info('✓ Connected to mittag_old database');
        } catch (\Exception $e) {
            $this->error('Cannot connect to mittag_old database: ' . $e->getMessage());

            return Command::FAILURE;
        }

        // Get all successful consultation migration mappings
        $query = DB::table('mittag_migration_logs')
            ->where('entity_type', 'consultation')
            ->where('status', 'success')
            ->whereNotNull('new_id');

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $total = (clone $query)->count();
        $this->info("Found {$total} migrated consultations to check");

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No data will be updated');
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('old_id')->chunk(200, function ($logs) use ($bar) {
            $oldIds = $logs->pluck('old_id')->toArray();

            // Batch fetch ODQ values from Mittag
            $mittagRecords = DB::connection('mittag_old')
                ->table('opd_consultation')
                ->whereIn('id', $oldIds)
                ->select('id', 'odq')
                ->get()
                ->keyBy('id');

            foreach ($logs as $log) {
                $mittagRecord = $mittagRecords->get($log->old_id);

                if (! $mittagRecord) {
                    $this->skipped++;
                    $bar->advance();

                    continue;
                }

                $odqValue = $this->cleanText($mittagRecord->odq);

                if (! $odqValue) {
                    $this->skipped++;
                    $bar->advance();

                    continue;
                }

                // Check if this is a migrated consultation
                $consultation = DB::table('consultations')
                    ->where('id', $log->new_id)
                    ->where('migrated_from_mittag', true)
                    ->first(['id', 'on_direct_questioning']);

                if (! $consultation) {
                    $this->skipped++;
                    $bar->advance();

                    continue;
                }

                // If it already has the correct ODQ value, skip
                if ($consultation->on_direct_questioning === $odqValue) {
                    $this->alreadyHasValue++;
                    $bar->advance();

                    continue;
                }

                if (! $this->option('dry-run')) {
                    DB::table('consultations')
                        ->where('id', $log->new_id)
                        ->where('migrated_from_mittag', true)
                        ->update(['on_direct_questioning' => $odqValue]);
                }

                $this->updated++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info('Backfill completed:');
        $this->line("  ✓ Updated:        {$this->updated}");
        $this->line("  ⊘ Skipped (null): {$this->skipped}");
        $this->line("  ● Already set:    {$this->alreadyHasValue}");

        return Command::SUCCESS;
    }

    private function cleanText(?string $text): ?string
    {
        if (! $text || trim($text) === '') {
            return null;
        }

        // Remove HTML tags and clean up
        $text = strip_tags($text);
        $text = html_entity_decode($text);
        $text = trim($text);

        return $text !== '' ? $text : null;
    }
}
