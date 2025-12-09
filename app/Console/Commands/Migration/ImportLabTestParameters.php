<?php

namespace App\Console\Commands\Migration;

use App\Models\LabService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLabTestParameters extends Command
{
    protected $signature = 'migrate:lab-parameters-from-mittag 
                            {--dry-run : Run without actually updating data}';

    protected $description = 'Import lab test parameters from Mittag lab_param_list into LabService test_parameters';

    private int $updated = 0;

    private int $skipped = 0;

    public function handle(): int
    {
        $this->info('Importing lab test parameters from Mittag...');

        try {
            DB::connection('mittag_old')->getPdo();
            $this->info('✓ Connected to mittag_old database');
        } catch (\Exception $e) {
            $this->error('Cannot connect to mittag_old database: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Get all parameters grouped by code
        $parameters = DB::connection('mittag_old')
            ->table('lab_param_list')
            ->select('code', 'name', 'standard')
            ->orderBy('code')
            ->orderBy('id')
            ->get()
            ->groupBy('code');

        $this->info('Found parameters for '.count($parameters).' lab tests');

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No data will be updated');
        }

        $bar = $this->output->createProgressBar(count($parameters));
        $bar->start();

        foreach ($parameters as $code => $params) {
            $this->updateLabService($code, $params);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Import completed:');
        $this->line("  ✓ Updated: {$this->updated}");
        $this->line("  ⊘ Skipped: {$this->skipped}");

        return Command::SUCCESS;
    }

    private function updateLabService(string $code, $params): void
    {
        $labService = LabService::where('code', $code)->first();

        if (! $labService) {
            $this->skipped++;

            return;
        }

        // Build test_parameters array
        $testParameters = [];
        foreach ($params as $param) {
            $paramData = [
                'name' => $this->slugify($param->name),
                'label' => trim($param->name),
                'type' => $this->determineType($param->name, $param->standard),
                'unit' => $this->extractUnit($param->standard),
            ];

            // Parse normal range if present
            $normalRange = $this->parseNormalRange($param->standard);
            if ($normalRange) {
                $paramData['normal_range'] = $normalRange;
            }

            $testParameters[] = $paramData;
        }

        if ($this->option('dry-run')) {
            $this->updated++;

            return;
        }

        $labService->update([
            'test_parameters' => ['parameters' => $testParameters],
        ]);

        $this->updated++;
    }

    private function slugify(string $name): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', trim($name)));
    }

    private function determineType(string $name, ?string $standard): string
    {
        $nameLower = strtolower($name);

        // Check for known select/option types
        if (in_array($nameLower, ['blood group', 'rh type', 'appearance', 'colour', 'color'])) {
            return 'select';
        }

        // Check if standard suggests numeric (has numbers or ranges)
        if ($standard && preg_match('/[\d.]+\s*[-–]\s*[\d.]+/', $standard)) {
            return 'numeric';
        }

        // Check for positive/negative type results
        if (in_array($nameLower, ['result', 'hbsag', 'vdrl', 'h.pylori test', 'hepatitis c'])) {
            return 'select';
        }

        // Default to text for flexibility
        return 'text';
    }

    private function extractUnit(?string $standard): ?string
    {
        if (! $standard) {
            return null;
        }

        // Common patterns: "12.0-16.0 g/dL", "3.3-6.6mmoI/L"
        if (preg_match('/[\d.]+\s*[-–]\s*[\d.]+\s*([a-zA-Z%\/\^]+.*)$/', $standard, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function parseNormalRange(?string $standard): ?array
    {
        if (! $standard) {
            return null;
        }

        // Try to extract min-max range: "12.0-16.0" or "3.3 - 6.6"
        if (preg_match('/([\d.]+)\s*[-–]\s*([\d.]+)/', $standard, $matches)) {
            return [
                'min' => (float) $matches[1],
                'max' => (float) $matches[2],
                'text' => trim($standard),
            ];
        }

        // If no numeric range, store as text reference
        if (trim($standard)) {
            return ['text' => trim($standard)];
        }

        return null;
    }
}
