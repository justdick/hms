<?php

namespace App\Console\Commands\Migration;

use App\Models\Drug;
use App\Models\Prescription;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigratePrescriptionsFromMittag extends Command
{
    protected $signature = 'migrate:prescriptions-from-mittag 
                            {--limit= : Limit number of records to migrate}
                            {--dry-run : Run without actually inserting data}
                            {--skip-existing : Skip prescriptions that already exist}';

    protected $description = 'Migrate prescriptions from Mittag opd_consultation to HMS';

    private int $migrated = 0;

    private int $skipped = 0;

    private int $failed = 0;

    private array $drugMap = [];

    private array $consultationMap = [];

    public function handle(): int
    {
        $this->info('Starting prescriptions migration from Mittag opd_consultation...');

        try {
            DB::connection('mittag_old')->getPdo();
            $this->info('✓ Connected to mittag_old database');
        } catch (\Exception $e) {
            $this->error('Cannot connect to mittag_old database: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Build mappings
        $this->buildDrugMap();
        $this->buildConsultationMap();

        $query = DB::connection('mittag_old')->table('opd_consultation');

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $total = DB::connection('mittag_old')->table('opd_consultation')->count();
        $this->info("Found {$total} consultations in Mittag");
        $this->info('Drug map size: '.count($this->drugMap));
        $this->info('Consultation map size: '.count($this->consultationMap));

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No data will be inserted');
        }

        $bar = $this->output->createProgressBar($this->option('limit') ? (int) $this->option('limit') : $total);
        $bar->start();

        $query->orderBy('id')->chunk(200, function ($consultations) use ($bar) {
            foreach ($consultations as $oldConsultation) {
                $this->migrateConsultationPrescriptions($oldConsultation);
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

    private function buildDrugMap(): void
    {
        $this->drugMap = Drug::pluck('id', 'drug_code')->toArray();
    }

    private function buildConsultationMap(): void
    {
        // Map old consultation (folder_id + date) to new consultation_id
        $logs = DB::table('mittag_migration_logs')
            ->where('entity_type', 'consultation')
            ->where('status', 'success')
            ->select('new_id', 'old_data')
            ->get();

        foreach ($logs as $log) {
            $oldData = json_decode($log->old_data, true);
            if ($oldData && isset($oldData['folder_id']) && isset($oldData['date'])) {
                $folderId = trim($oldData['folder_id']);
                $date = $oldData['date'];
                $key = "{$folderId}_{$date}";
                $this->consultationMap[$key] = $log->new_id;
            }
        }
    }

    private function migrateConsultationPrescriptions(object $old): void
    {
        // Find the new consultation
        $folderId = trim($old->folder_id);
        $key = "{$folderId}_{$old->date}";
        $consultationId = $this->consultationMap[$key] ?? null;

        if (! $consultationId) {
            // No matching consultation, skip all drugs
            $this->skipped++;

            return;
        }

        // Always check if already migrated
        $existingLog = DB::table('mittag_migration_logs')
            ->where('entity_type', 'prescription_batch')
            ->where('old_id', $old->id)
            ->where('status', 'success')
            ->first();

        if ($existingLog) {
            $this->skipped++;

            return;
        }

        // Validate date
        if (empty($old->date) || $old->date === '0000-00-00') {
            return;
        }

        try {
            $prescribedAt = Carbon::parse($old->date);
            if ($prescribedAt->year < 2000 || $prescribedAt->year > 2030) {
                return;
            }
        } catch (\Exception $e) {
            return;
        }

        $prescriptionsCreated = 0;

        // Process up to 20 drugs
        for ($i = 1; $i <= 20; $i++) {
            $drugCode = $old->{"drug{$i}"} ?? '';
            if (empty($drugCode)) {
                continue;
            }

            $drugId = $this->drugMap[$drugCode] ?? null;
            $unparsed = $old->{"unparsed{$i}"} ?? '';
            $dose = $old->{"dose{$i}"} ?? 0;
            $frequency = $old->{"frequency{$i}"} ?? 0;
            $duration = $old->{"duration{$i}"} ?? 0;
            $served = $old->{"drug{$i}_served"} ?? 0;

            // Parse frequency to text
            $frequencyText = $this->parseFrequency($frequency, $unparsed);
            $durationText = $this->parseDuration($duration, $unparsed);
            $doseText = $this->parseDose($dose, $unparsed);

            if ($this->option('dry-run')) {
                $prescriptionsCreated++;

                continue;
            }

            try {
                $drug = $drugId ? Drug::find($drugId) : null;

                $prescriptionData = [
                    'consultation_id' => $consultationId,
                    'prescribable_type' => 'App\\Models\\Consultation',
                    'prescribable_id' => $consultationId,
                    'drug_id' => $drugId,
                    'medication_name' => $drug?->name ?? $drugCode,
                    'dosage_form' => $drug?->dosage_form ?? 'Tablet',
                    'frequency' => $frequencyText,
                    'duration' => $durationText,
                    'dose_quantity' => $doseText,
                    'quantity' => max(1, (int) ($frequency * $duration)),
                    'quantity_to_dispense' => max(1, (int) ($frequency * $duration)),
                    'quantity_dispensed' => $served ? max(1, (int) ($frequency * $duration)) : 0,
                    'instructions' => $unparsed ?: 'As directed',
                    'status' => $served ? 'dispensed' : 'prescribed',
                    'reviewed_by' => $served ? 1 : null,
                    'reviewed_at' => $served ? $prescribedAt : null,
                    'dispensing_notes' => $served ? 'Migrated from Mittag' : null,
                    'created_at' => $prescribedAt,
                    'updated_at' => $prescribedAt,
                    'migrated_from_mittag' => true,
                ];

                // Create without triggering events (avoids charge creation issues)
                Prescription::withoutEvents(function () use ($prescriptionData) {
                    Prescription::create($prescriptionData);
                });
                $prescriptionsCreated++;

            } catch (\Exception $e) {
                Log::error('Prescription migration failed', [
                    'consultation_id' => $old->id,
                    'drug_index' => $i,
                    'drug_code' => $drugCode,
                    'error' => $e->getMessage(),
                ]);
                $this->failed++;
            }
        }

        if ($prescriptionsCreated > 0) {
            $this->migrated += $prescriptionsCreated;

            if (! $this->option('dry-run')) {
                $this->logMigration($old, $consultationId, 'success', "Created {$prescriptionsCreated} prescriptions");
            }
        }
    }

    private function parseFrequency(int $frequency, string $unparsed): string
    {
        // Try to extract from unparsed text first
        $unparsedLower = strtolower($unparsed);

        if (str_contains($unparsedLower, 'stat')) {
            return 'STAT';
        }
        if (str_contains($unparsedLower, 'prn')) {
            return 'PRN';
        }
        if (str_contains($unparsedLower, 'qid') || str_contains($unparsedLower, '4 times')) {
            return 'QID (4 times daily)';
        }
        if (str_contains($unparsedLower, 'tds') || str_contains($unparsedLower, 'tid') || str_contains($unparsedLower, '3 times')) {
            return 'TDS (3 times daily)';
        }
        if (str_contains($unparsedLower, 'bd') || str_contains($unparsedLower, 'bid') || str_contains($unparsedLower, '2 times') || str_contains($unparsedLower, 'twice')) {
            return 'BD (twice daily)';
        }
        if (str_contains($unparsedLower, 'od') || str_contains($unparsedLower, 'daily') || str_contains($unparsedLower, 'once')) {
            return 'OD (once daily)';
        }
        if (str_contains($unparsedLower, 'nocte') || str_contains($unparsedLower, 'night')) {
            return 'Nocte (at night)';
        }

        // Fall back to numeric frequency
        return match ($frequency) {
            1 => 'OD (once daily)',
            2 => 'BD (twice daily)',
            3 => 'TDS (3 times daily)',
            4 => 'QID (4 times daily)',
            default => 'As directed',
        };
    }

    private function parseDuration(int $duration, string $unparsed): string
    {
        // Try to extract from unparsed (e.g., "x 7", "x7", "7 days")
        if (preg_match('/x\s*(\d+)/i', $unparsed, $matches)) {
            return $matches[1].' days';
        }
        if (preg_match('/(\d+)\s*days?/i', $unparsed, $matches)) {
            return $matches[1].' days';
        }
        if (preg_match('/(\d+)\s*hrs?/i', $unparsed, $matches)) {
            return $matches[1].' hours';
        }
        if (preg_match('/(\d+)\s*weeks?/i', $unparsed, $matches)) {
            return $matches[1].' weeks';
        }

        if ($duration > 0) {
            return $duration.' days';
        }

        // Default to a sensible value - never return null
        return '1 course';
    }

    private function parseDose(float $dose, string $unparsed): string
    {
        // Try to extract from unparsed (e.g., "500mg", "2.5ml", "1 tab")
        if (preg_match('/(\d+(?:\.\d+)?)\s*(mg|ml|g|tab|caps?|sachet)/i', $unparsed, $matches)) {
            return $matches[1].$matches[2];
        }

        if ($dose > 0) {
            return (string) $dose;
        }

        return '1';
    }

    private function logMigration(object $old, ?int $newId, string $status, ?string $notes = null): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        DB::table('mittag_migration_logs')->updateOrInsert(
            [
                'entity_type' => 'prescription_batch',
                'old_id' => $old->id,
            ],
            [
                'new_id' => $newId,
                'old_identifier' => $old->folder_id,
                'new_identifier' => $newId,
                'status' => $status,
                'notes' => $notes ? substr($notes, 0, 500) : null,
                'old_data' => json_encode([
                    'folder_id' => $old->folder_id,
                    'date' => $old->date,
                ]),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
