<?php

namespace App\Console\Commands\Migration;

use App\Models\Ward;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateWardsFromMittag extends Command
{
    protected $signature = 'migrate:wards-from-mittag 
                            {--dry-run : Run without actually inserting data}
                            {--skip-existing : Skip wards that already exist}';

    protected $description = 'Migrate wards from Mittag old system to HMS';

    private int $migrated = 0;

    private int $skipped = 0;

    private int $failed = 0;

    public function handle(): int
    {
        $this->info('Starting ward migration from Mittag...');

        try {
            DB::connection('mittag_old')->getPdo();
            $this->info('✓ Connected to mittag_old database');
        } catch (\Exception $e) {
            $this->error('Cannot connect to mittag_old database: '.$e->getMessage());

            return Command::FAILURE;
        }

        $wards = DB::connection('mittag_old')->table('wards')->orderBy('id')->get();

        $this->info("Found {$wards->count()} wards in Mittag");

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No data will be inserted');
        }

        foreach ($wards as $oldWard) {
            $this->migrateWard($oldWard);
        }

        $this->newLine();
        $this->info('Migration completed:');
        $this->line("  ✓ Migrated: {$this->migrated}");
        $this->line("  ⊘ Skipped:  {$this->skipped}");
        $this->line("  ✗ Failed:   {$this->failed}");

        return Command::SUCCESS;
    }

    private function migrateWard(object $old): void
    {
        try {
            // Check if already exists by name or ID
            if ($this->option('skip-existing')) {
                $existing = Ward::where('id', $old->id)
                    ->orWhere('name', $old->name)
                    ->first();

                if ($existing) {
                    $this->line("  ⊘ Skipping existing ward: {$old->name}");
                    $this->skipped++;

                    return;
                }
            }

            if ($this->option('dry-run')) {
                $this->line("  → Would migrate: {$old->name} (capacity: {$old->capacity})");
                $this->migrated++;

                return;
            }

            // Generate code from name
            $code = Str::upper(Str::slug($old->name, '-'));

            // Insert with specific ID to maintain mapping
            DB::table('wards')->insert([
                'id' => $old->id,
                'name' => $old->name,
                'code' => $code,
                'description' => 'Migrated from Mittag',
                'total_beds' => $old->capacity,
                'available_beds' => $old->capacity,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create individual bed records for this ward
            $this->createBedsForWard($old->id, $old->capacity);

            $this->line("  ✓ Migrated: {$old->name} (ID: {$old->id}, capacity: {$old->capacity}, beds created: {$old->capacity})");
            $this->logMigration($old, $old->id, 'success');
            $this->migrated++;

        } catch (\Exception $e) {
            $this->error("  ✗ Failed to migrate {$old->name}: {$e->getMessage()}");
            $this->logMigration($old, null, 'failed', $e->getMessage());
            $this->failed++;
        }
    }

    private function createBedsForWard(int $wardId, int $capacity): void
    {
        for ($i = 1; $i <= $capacity; $i++) {
            $bedNumber = str_pad($i, 2, '0', STR_PAD_LEFT);

            DB::table('beds')->insert([
                'ward_id' => $wardId,
                'bed_number' => $bedNumber,
                'status' => 'available',
                'type' => 'standard',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function logMigration(object $old, ?int $newId, string $status, ?string $notes = null): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        DB::table('mittag_migration_logs')->updateOrInsert(
            [
                'entity_type' => 'ward',
                'old_id' => $old->id,
            ],
            [
                'new_id' => $newId,
                'old_identifier' => $old->name,
                'new_identifier' => $old->name,
                'status' => $status,
                'notes' => $notes ? substr($notes, 0, 500) : null,
                'old_data' => json_encode([
                    'id' => $old->id,
                    'name' => $old->name,
                    'capacity' => $old->capacity,
                ]),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
