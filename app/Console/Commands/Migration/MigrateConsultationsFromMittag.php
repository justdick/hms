<?php

namespace App\Console\Commands\Migration;

use App\Models\Consultation;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateConsultationsFromMittag extends Command
{
    protected $signature = 'migrate:consultations-from-mittag 
                            {--limit= : Limit number of records to migrate}
                            {--dry-run : Run without actually inserting data}
                            {--skip-existing : Skip consultations that already exist}';

    protected $description = 'Migrate consultations from Mittag old system to HMS';

    private int $migrated = 0;

    private int $skipped = 0;

    private int $failed = 0;

    private array $checkinMap = [];

    public function handle(): int
    {
        $this->info('Starting consultation migration from Mittag...');

        try {
            DB::connection('mittag_old')->getPdo();
            $this->info('✓ Connected to mittag_old database');
        } catch (\Exception $e) {
            $this->error('Cannot connect to mittag_old database: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Build checkin mapping (old_id -> new_id from migration logs)
        $this->buildCheckinMap();

        $query = DB::connection('mittag_old')->table('opd_consultation');

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $total = DB::connection('mittag_old')->table('opd_consultation')->count();
        $this->info("Found {$total} consultations in Mittag database");
        $this->info('Checkin map size: '.count($this->checkinMap));

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No data will be inserted');
        }

        $bar = $this->output->createProgressBar($this->option('limit') ? (int) $this->option('limit') : $total);
        $bar->start();

        $query->orderBy('id')->chunk(100, function ($consultations) use ($bar) {
            foreach ($consultations as $oldConsultation) {
                $this->migrateConsultation($oldConsultation);
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

    private function buildCheckinMap(): void
    {
        // Map folder_id + date to new checkin id
        // First get all successful checkin migrations with their old_data
        $logs = DB::table('mittag_migration_logs')
            ->where('entity_type', 'checkin')
            ->where('status', 'success')
            ->select('old_id', 'new_id', 'old_identifier', 'old_data')
            ->get();

        foreach ($logs as $log) {
            $oldData = json_decode($log->old_data, true);
            if ($oldData && isset($oldData['folder_id']) && isset($oldData['date'])) {
                $folderId = trim($oldData['folder_id']);
                $date = $oldData['date'];
                $key = "{$folderId}_{$date}";
                $this->checkinMap[$key] = $log->new_id;
            }
        }
    }

    private function migrateConsultation(object $old): void
    {
        try {
            // Check if already migrated
            if ($this->option('skip-existing')) {
                $existingLog = DB::table('mittag_migration_logs')
                    ->where('entity_type', 'consultation')
                    ->where('old_id', $old->id)
                    ->where('status', 'success')
                    ->first();

                if ($existingLog) {
                    $this->skipped++;

                    return;
                }
            }

            // Validate date first
            $consultationDate = $old->date && $old->date !== '0000-00-00'
                ? Carbon::parse($old->date)
                : null;

            if (! $consultationDate) {
                $this->logMigration($old, null, null, 'failed', "Invalid date: {$old->date}");
                $this->saveOrphanedRecord($old, 'invalid_date');
                $this->failed++;

                return;
            }

            // Find the corresponding checkin using folder_id + date
            $folderId = trim($old->folder_id);
            $key = "{$folderId}_{$old->date}";
            $checkinId = $this->checkinMap[$key] ?? null;

            if (! $checkinId) {
                $this->logMigration($old, null, null, 'failed', "Checkin not found for folder_id: {$old->folder_id}, date: {$old->date}");
                $this->saveOrphanedRecord($old, 'checkin_not_found');
                $this->failed++;

                return;
            }

            if ($this->option('dry-run')) {
                $this->migrated++;

                return;
            }

            $consultationData = [
                'patient_checkin_id' => $checkinId,
                'doctor_id' => 1, // System user
                'started_at' => $consultationDate,
                'completed_at' => $consultationDate->copy()->addHour(),
                'status' => 'completed',
                'presenting_complaint' => $this->cleanText($old->pc),
                'history_presenting_complaint' => $this->cleanText($old->hpc),
                'on_direct_questioning' => $this->buildOnDirectQuestioning($old),
                'examination_findings' => $this->cleanText($old->oe),
                'assessment_notes' => $this->extractDiagnoses($old),
                'plan_notes' => $this->cleanText($old->plan),
                'created_at' => $consultationDate,
                'updated_at' => $consultationDate,
                'migrated_from_mittag' => true,
            ];

            $consultation = Consultation::create($consultationData);

            $this->logMigration($old, $consultation->id, $consultation->id, 'success');
            $this->migrated++;

        } catch (\Exception $e) {
            $this->logMigration($old, null, null, 'failed', $e->getMessage());
            $this->saveOrphanedRecord($old, 'error', $e->getMessage());
            $this->failed++;
            Log::error('Consultation migration failed', [
                'old_id' => $old->id,
                'request_id' => $old->request_id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function cleanText(?string $text): ?string
    {
        if (! $text || trim($text) === '') {
            return null;
        }

        // Remove HTML tags and clean up
        $text = strip_tags($text);
        $text = html_entity_decode($text);

        return trim($text);
    }

    private function buildOnDirectQuestioning(object $old): ?string
    {
        $parts = [];

        if ($pmh = $this->cleanText($old->pmh ?? '')) {
            $parts[] = "PMH: {$pmh}";
        }
        if ($dh = $this->cleanText($old->dh ?? '')) {
            $parts[] = "Drug History: {$dh}";
        }
        if ($fh = $this->cleanText($old->fh ?? '')) {
            $parts[] = "Family History: {$fh}";
        }
        if ($sh = $this->cleanText($old->sh ?? '')) {
            $parts[] = "Social History: {$sh}";
        }

        return $parts ? implode("\n", $parts) : null;
    }

    private function extractDiagnoses(object $old): ?string
    {
        $diagnoses = [];

        // Extract from pro1-pro4 (procedures/diagnoses)
        for ($i = 1; $i <= 4; $i++) {
            $pro = $old->{"pro{$i}"} ?? '';
            if ($pro && trim($pro) !== '') {
                $diagnoses[] = trim($pro);
            }
        }

        // Extract from pri1-pri4 (primary diagnoses)
        for ($i = 1; $i <= 4; $i++) {
            $pri = $old->{"pri{$i}"} ?? '';
            if ($pri && trim($pri) !== '') {
                $diagnoses[] = trim($pri);
            }
        }

        return $diagnoses ? implode('; ', array_unique($diagnoses)) : null;
    }

    private function saveOrphanedRecord(object $old, string $reason, ?string $notes = null): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        DB::table('mittag_orphaned_records')->updateOrInsert(
            [
                'entity_type' => 'consultation',
                'old_id' => $old->id,
            ],
            [
                'old_identifier' => $old->folder_id ?? $old->request_id ?? null,
                'reason' => $reason,
                'full_data' => json_encode((array) $old),
                'notes' => $notes,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function logMigration(object $old, ?int $newId, ?string $newIdentifier, string $status, ?string $notes = null): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        DB::table('mittag_migration_logs')->updateOrInsert(
            [
                'entity_type' => 'consultation',
                'old_id' => $old->id,
            ],
            [
                'new_id' => $newId,
                'old_identifier' => $old->folder_id ?? null,
                'new_identifier' => $newIdentifier,
                'status' => $status,
                'notes' => $notes ? substr($notes, 0, 500) : null, // Truncate notes to avoid overflow
                'old_data' => json_encode([
                    'folder_id' => $old->folder_id ?? null,
                    'clinic' => $old->clinic ?? null,
                    'date' => $old->date ?? null,
                    'request_id' => $old->request_id ?? null,
                ]),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
