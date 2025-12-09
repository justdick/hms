<?php

namespace App\Console\Commands\Migration;

use App\Models\LabService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateLabServicesFromMittag extends Command
{
    protected $signature = 'migrate:lab-services-from-mittag 
                            {--dry-run : Run without actually inserting data}
                            {--skip-existing : Skip services that already exist by code}';

    protected $description = 'Migrate lab services from Mittag GDRG tariffs to HMS';

    private int $migrated = 0;

    private int $skipped = 0;

    private int $failed = 0;

    public function handle(): int
    {
        $this->info('Starting lab services migration from Mittag...');

        try {
            DB::connection('mittag_old')->getPdo();
            $this->info('✓ Connected to mittag_old database');
        } catch (\Exception $e) {
            $this->error('Cannot connect to mittag_old database: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Get only lab tests that are actually used in lab_daily_register
        $usedCodes = DB::connection('mittag_old')
            ->table('lab_daily_register')
            ->distinct()
            ->pluck('code')
            ->toArray();

        $this->info('Found '.count($usedCodes).' unique lab tests used in orders');

        // Get lab services from gdrg_lab
        $labServices = DB::connection('mittag_old')
            ->table('gdrg_lab')
            ->whereIn('code', $usedCodes)
            ->get();

        $this->info('Found '.$labServices->count().' lab services to migrate');

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No data will be inserted');
        }

        $bar = $this->output->createProgressBar($labServices->count());
        $bar->start();

        foreach ($labServices as $oldService) {
            $this->migrateLabService($oldService);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Migration completed:');
        $this->line("  ✓ Migrated: {$this->migrated}");
        $this->line("  ⊘ Skipped:  {$this->skipped}");
        $this->line("  ✗ Failed:   {$this->failed}");

        return Command::SUCCESS;
    }

    private function migrateLabService(object $old): void
    {
        try {
            // Check if already exists
            if ($this->option('skip-existing')) {
                $existing = LabService::where('code', $old->code)->first();
                if ($existing) {
                    $this->skipped++;

                    return;
                }
            }

            if ($this->option('dry-run')) {
                $this->migrated++;

                return;
            }

            // Clean the name (remove HTML entities)
            $name = html_entity_decode($old->name);
            $name = trim($name);

            // Determine category based on test name
            $category = $this->determineCategory($name, $old->code);

            // Determine sample type
            $sampleType = $this->determineSampleType($name);

            // Use cash_price for uninsured patients (NHIS tariff is handled separately via mappings)
            $cashPrice = $old->cash_price ?? 0;

            LabService::updateOrCreate(
                ['code' => $old->code],
                [
                    'name' => $name,
                    'category' => $category,
                    'description' => 'Migrated from Mittag GDRG tariffs',
                    'price' => $cashPrice,
                    'sample_type' => $sampleType,
                    'turnaround_time' => '24 hours',
                    'is_active' => true,
                ]
            );
            $this->migrated++;

        } catch (\Exception $e) {
            $this->failed++;
            Log::error('Lab service migration failed', [
                'code' => $old->code,
                'name' => $old->name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function determineCategory(string $name, string $code): string
    {
        $nameLower = strtolower($name);

        return match (true) {
            str_contains($nameLower, 'blood count') || str_contains($nameLower, 'fbc') ||
            str_contains($nameLower, 'haemoglobin') || str_contains($nameLower, 'sickling') ||
            str_contains($nameLower, 'esr') || str_contains($nameLower, 'platelet') => 'Hematology',

            str_contains($nameLower, 'urine') => 'Urinalysis',

            str_contains($nameLower, 'malaria') || str_contains($nameLower, 'widal') ||
            str_contains($nameLower, 'helicobacter') || str_contains($nameLower, 'hiv') ||
            str_contains($nameLower, 'hepatitis') || str_contains($nameLower, 'vdrl') => 'Microbiology',

            str_contains($nameLower, 'liver') || str_contains($nameLower, 'renal') ||
            str_contains($nameLower, 'lipid') || str_contains($nameLower, 'glucose') ||
            str_contains($nameLower, 'thyroid') || str_contains($nameLower, 'psa') => 'Chemistry',

            str_contains($nameLower, 'pregnancy') => 'Immunology',

            str_contains($nameLower, 'blood group') || str_contains($nameLower, 'rh') ||
            str_contains($nameLower, 'matching') => 'Blood Bank',

            default => 'Chemistry',
        };
    }

    private function determineSampleType(string $name): string
    {
        $nameLower = strtolower($name);

        return match (true) {
            str_contains($nameLower, 'urine') => 'Urine',
            str_contains($nameLower, 'stool') => 'Stool',
            str_contains($nameLower, 'sputum') => 'Sputum',
            str_contains($nameLower, 'csf') => 'CSF',
            default => 'Blood',
        };
    }
}
