<?php

namespace App\Console\Commands\Migration;

use App\Models\Drug;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateDrugsFromMittag extends Command
{
    protected $signature = 'migrate:drugs-from-mittag 
                            {--limit= : Limit number of records to migrate}
                            {--dry-run : Run without actually inserting data}
                            {--skip-existing : Skip drugs that already exist by code}';

    protected $description = 'Migrate drugs from Mittag old system to HMS';

    private int $migrated = 0;

    private int $skipped = 0;

    private int $failed = 0;

    public function handle(): int
    {
        $this->info('Starting drug migration from Mittag...');

        try {
            DB::connection('mittag_old')->getPdo();
            $this->info('✓ Connected to mittag_old database');
        } catch (\Exception $e) {
            $this->error('Cannot connect to mittag_old database: '.$e->getMessage());

            return Command::FAILURE;
        }

        $query = DB::connection('mittag_old')->table('drugs');

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $total = DB::connection('mittag_old')->table('drugs')->count();
        $this->info("Found {$total} drugs in Mittag database");

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No data will be inserted');
        }

        $bar = $this->output->createProgressBar($this->option('limit') ? (int) $this->option('limit') : $total);
        $bar->start();

        $query->orderBy('id')->chunk(100, function ($drugs) use ($bar) {
            foreach ($drugs as $oldDrug) {
                $this->migrateDrug($oldDrug);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info('Migration completed:');
        $this->line("  ✓ Migrated: {$this->migrated}");
        $this->line("  ⊘ Skipped:  {$this->skipped}");
        $this->line("  ✗ Failed:   {$this->failed}");

        return Command::SUCCESS;
    }

    private function migrateDrug(object $old): void
    {
        try {
            // Check if already migrated
            $existingLog = DB::table('mittag_migration_logs')
                ->where('entity_type', 'drug')
                ->where('old_id', $old->id)
                ->first();

            if ($existingLog && $existingLog->status === 'success') {
                $this->skipped++;

                return;
            }

            // Check if drug exists by code
            if ($this->option('skip-existing')) {
                $existing = Drug::where('drug_code', $old->code)->first();

                if ($existing) {
                    $this->logMigration($old, $existing->id, $existing->drug_code, 'skipped', 'Drug already exists');
                    $this->skipped++;

                    return;
                }
            }

            if ($this->option('dry-run')) {
                $this->migrated++;

                return;
            }

            $drugData = $this->mapDrugData($old);
            $drugCode = $drugData['drug_code'];

            // Disable observers during migration to avoid notification errors
            $drug = Drug::withoutEvents(function () use ($drugCode, $drugData) {
                return Drug::updateOrCreate(
                    ['drug_code' => $drugCode],
                    $drugData
                );
            });

            $this->logMigration($old, $drug->id, $drug->drug_code, 'success');
            $this->migrated++;

        } catch (\Exception $e) {
            $this->logMigration($old, null, null, 'failed', $e->getMessage());
            $this->failed++;
            Log::error('Drug migration failed', [
                'old_id' => $old->id,
                'code' => $old->code,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function mapDrugData(object $old): array
    {
        $name = trim($old->name) ?: 'Unknown Drug';

        // Determine category based on old system flags
        $category = $this->determineCategory($old);

        // Determine form based on costing_unit or name patterns
        $form = $this->determineForm($old);

        return [
            'name' => $name,
            'generic_name' => $name, // Old system doesn't separate generic/brand
            'brand_name' => null,
            'drug_code' => $old->code ?: 'DRUG-'.$old->id,
            'category' => $category,
            'form' => $form,
            'strength' => $old->dose ? (string) $old->dose : null,
            'description' => null,
            'unit_price' => $old->cash_price ?: 0, // Only use cash_price, not cost (NHIS tariff handled separately)
            'unit_type' => $this->mapUnitType($old->costing_unit),
            'minimum_stock_level' => 10,
            'maximum_stock_level' => 1000,
            'is_active' => true,
            'migrated_from_mittag' => true,
        ];
    }

    private function determineCategory(object $old): string
    {
        $name = strtolower($old->name ?? '');
        $category = strtolower($old->category ?? '');

        // Valid enum values: analgesics, antibiotics, antivirals, antifungals, cardiovascular,
        // diabetes, respiratory, gastrointestinal, neurological, psychiatric, dermatological,
        // vaccines, vitamins, supplements, other

        // Check for cream flag
        if ($old->cream) {
            return 'dermatological';
        }

        // Pattern matching on name/category
        return match (true) {
            str_contains($name, 'antibiotic') || str_contains($category, 'antibiotic') => 'antibiotics',
            str_contains($name, 'paracetamol') || str_contains($name, 'ibuprofen') || str_contains($name, 'diclofenac') => 'analgesics',
            str_contains($name, 'vitamin') => 'vitamins',
            str_contains($name, 'cream') || str_contains($name, 'ointment') => 'dermatological',
            str_contains($name, 'antifungal') || str_contains($name, 'fluconazole') => 'antifungals',
            str_contains($name, 'antiviral') || str_contains($name, 'acyclovir') => 'antivirals',
            str_contains($name, 'omeprazole') || str_contains($name, 'antacid') => 'gastrointestinal',
            str_contains($name, 'metformin') || str_contains($name, 'insulin') => 'diabetes',
            str_contains($name, 'amlodipine') || str_contains($name, 'lisinopril') => 'cardiovascular',
            str_contains($name, 'salbutamol') || str_contains($name, 'inhaler') => 'respiratory',
            str_contains($name, 'vaccine') => 'vaccines',
            str_contains($name, 'supplement') => 'supplements',
            default => 'other',
        };
    }

    private function determineForm(object $old): string
    {
        $name = strtolower($old->name ?? '');
        $unit = strtolower($old->costing_unit ?? '');

        return match (true) {
            str_contains($name, 'tablet') || str_contains($unit, 'tab') => 'tablet',
            str_contains($name, 'capsule') || str_contains($unit, 'cap') => 'capsule',
            str_contains($name, 'syrup') || str_contains($name, 'mixture') || str_contains($unit, 'bottle') => 'syrup',
            str_contains($name, 'suspension') => 'suspension',
            str_contains($name, 'cream') || str_contains($name, 'ointment') => 'cream',
            str_contains($name, 'injection') || str_contains($name, 'inj') => 'injection',
            str_contains($name, 'drop') => 'drops',
            default => 'tablet',
        };
    }

    private function mapUnitType(?string $costingUnit): string
    {
        // Valid enum: piece, bottle, vial, tube, box
        $unit = strtolower(trim($costingUnit ?? ''));

        return match (true) {
            str_contains($unit, 'bottle') || str_contains($unit, 'btl') => 'bottle',
            str_contains($unit, 'vial') => 'vial',
            str_contains($unit, 'tube') => 'tube',
            str_contains($unit, 'box') => 'box',
            default => 'piece',
        };
    }

    private function logMigration(object $old, ?int $newId, ?string $newIdentifier, string $status, ?string $notes = null): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        DB::table('mittag_migration_logs')->updateOrInsert(
            [
                'entity_type' => 'drug',
                'old_id' => $old->id,
            ],
            [
                'new_id' => $newId,
                'old_identifier' => $old->code,
                'new_identifier' => $newIdentifier,
                'status' => $status,
                'notes' => $notes,
                'old_data' => json_encode([
                    'code' => $old->code,
                    'name' => $old->name,
                    'cost' => $old->cost,
                    'tariff' => $old->tariff,
                    'cash_price' => $old->cash_price,
                    'stock' => $old->stock,
                    'category' => $old->category,
                ]),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
