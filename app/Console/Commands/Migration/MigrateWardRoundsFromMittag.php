<?php

namespace App\Console\Commands\Migration;

use App\Models\PatientAdmission;
use App\Models\WardRound;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateWardRoundsFromMittag extends Command
{
    protected $signature = 'migrate:ward-rounds-from-mittag 
                            {--limit= : Limit number of records to migrate}
                            {--dry-run : Run without actually inserting data}
                            {--skip-existing : Skip ward rounds that already exist}';

    protected $description = 'Migrate ward rounds from Mittag old system to HMS';

    private int $migrated = 0;

    private int $skipped = 0;

    private int $failed = 0;

    private array $admissionMap = [];

    public function handle(): int
    {
        $this->info('Starting ward rounds migration from Mittag...');

        try {
            DB::connection('mittag_old')->getPdo();
            $this->info('✓ Connected to mittag_old database');
        } catch (\Exception $e) {
            $this->error('Cannot connect to mittag_old database: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Build admission mapping
        $this->buildAdmissionMap();

        $query = DB::connection('mittag_old')->table('ipd_review');

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $total = DB::connection('mittag_old')->table('ipd_review')->count();
        $this->info("Found {$total} ward rounds in Mittag ipd_review");
        $this->info('Admission map size: '.count($this->admissionMap));

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No data will be inserted');
        }

        $bar = $this->output->createProgressBar($this->option('limit') ? (int) $this->option('limit') : $total);
        $bar->start();

        $query->orderBy('id')->chunk(100, function ($reviews) use ($bar) {
            foreach ($reviews as $oldReview) {
                $this->migrateWardRound($oldReview);
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

    private function buildAdmissionMap(): void
    {
        // Map folder_id to admission_id (get the admission that covers the review date)
        $logs = DB::table('mittag_migration_logs')
            ->where('entity_type', 'admission')
            ->where('status', 'success')
            ->select('new_id', 'old_data')
            ->get();

        foreach ($logs as $log) {
            $oldData = json_decode($log->old_data, true);
            if ($oldData && isset($oldData['folder_id'])) {
                $folderId = trim($oldData['folder_id']);
                $dateAdmitted = $oldData['date_admitted'] ?? null;
                $dateDischarged = $oldData['date_discharged'] ?? null;

                // Store with date range for matching
                $this->admissionMap[$folderId][] = [
                    'admission_id' => $log->new_id,
                    'date_admitted' => $dateAdmitted,
                    'date_discharged' => $dateDischarged,
                ];
            }
        }
    }

    private function findAdmissionForReview(string $folderId, string $reviewDate): ?int
    {
        if (! isset($this->admissionMap[$folderId])) {
            return null;
        }

        foreach ($this->admissionMap[$folderId] as $admission) {
            $admitted = $admission['date_admitted'];
            $discharged = $admission['date_discharged'];

            // Check if review date falls within admission period
            if ($reviewDate >= $admitted) {
                if (! $discharged || $discharged === '0000-00-00' || $reviewDate <= $discharged) {
                    return $admission['admission_id'];
                }
            }
        }

        // If no exact match, return the most recent admission for this patient
        $admissions = $this->admissionMap[$folderId];
        usort($admissions, fn ($a, $b) => $b['date_admitted'] <=> $a['date_admitted']);

        return $admissions[0]['admission_id'] ?? null;
    }

    private function migrateWardRound(object $old): void
    {
        try {
            // Check if already migrated
            if ($this->option('skip-existing')) {
                $existingLog = DB::table('mittag_migration_logs')
                    ->where('entity_type', 'ward_round')
                    ->where('old_id', $old->id)
                    ->where('status', 'success')
                    ->first();

                if ($existingLog) {
                    $this->skipped++;

                    return;
                }
            }

            // Find admission for this review
            $folderId = trim($old->folder_id);
            $admissionId = $this->findAdmissionForReview($folderId, $old->date);

            if (! $admissionId) {
                $this->logMigration($old, null, 'failed', "Admission not found for {$folderId} on {$old->date}");
                $this->saveOrphanedRecord($old, 'admission_not_found');
                $this->failed++;

                return;
            }

            if ($this->option('dry-run')) {
                $this->migrated++;

                return;
            }

            // Get admission to calculate day number
            $admission = PatientAdmission::find($admissionId);
            $reviewDate = Carbon::parse($old->date);
            $dayNumber = $admission ? $reviewDate->diffInDays(Carbon::parse($admission->admitted_at)) + 1 : 1;
            // Ensure day_number is always positive
            $dayNumber = max(1, $dayNumber);

            // Build SOAP notes from old fields
            $presentingComplaint = $this->cleanHtml($old->pc);
            $historyPresentingComplaint = $this->cleanHtml($old->hpc);
            $onDirectQuestioning = $this->buildOnDirectQuestioning($old);
            $examinationFindings = $this->cleanHtml($old->oe);
            $assessmentNotes = $this->extractDiagnoses($old);
            $planNotes = $this->cleanHtml($old->plan);

            $roundDatetime = Carbon::parse($old->timestamp ?? $old->date);

            $wardRoundData = [
                'patient_admission_id' => $admissionId,
                'doctor_id' => 1, // System user
                'day_number' => $dayNumber,
                'round_type' => 'daily_round',
                'presenting_complaint' => $presentingComplaint,
                'history_presenting_complaint' => $historyPresentingComplaint,
                'on_direct_questioning' => $onDirectQuestioning,
                'examination_findings' => $examinationFindings,
                'assessment_notes' => $assessmentNotes,
                'plan_notes' => $planNotes,
                'patient_status' => 'stable',
                'round_datetime' => $roundDatetime,
                'status' => 'completed',
                'created_at' => $roundDatetime,
                'updated_at' => $roundDatetime,
                'migrated_from_mittag' => true,
            ];

            $wardRound = WardRound::create($wardRoundData);

            $this->logMigration($old, $wardRound->id, 'success');
            $this->migrated++;

        } catch (\Exception $e) {
            $this->logMigration($old, null, 'failed', $e->getMessage());
            $this->saveOrphanedRecord($old, 'error', $e->getMessage());
            $this->failed++;
            Log::error('Ward round migration failed', [
                'old_id' => $old->id,
                'folder_id' => $old->folder_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function cleanHtml(?string $text): ?string
    {
        if (! $text || trim($text) === '' || trim($text) === '<p><br></p>') {
            return null;
        }

        $text = strip_tags($text);
        $text = html_entity_decode($text);
        // Remove invalid UTF-8 characters
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        $text = trim($text);

        return $text ?: null;
    }

    private function buildOnDirectQuestioning(object $old): ?string
    {
        $parts = [];

        if ($odq = $this->cleanHtml($old->odq ?? '')) {
            $parts[] = $odq;
        }
        if ($pmh = $this->cleanHtml($old->pmh ?? '')) {
            $parts[] = "PMH: {$pmh}";
        }
        if ($dh = $this->cleanHtml($old->dh ?? '')) {
            $parts[] = "Drug History: {$dh}";
        }
        if ($fh = $this->cleanHtml($old->fh ?? '')) {
            $parts[] = "Family History: {$fh}";
        }
        if ($sh = $this->cleanHtml($old->sh ?? '')) {
            $parts[] = "Social History: {$sh}";
        }

        return $parts ? implode("\n", $parts) : null;
    }

    private function extractDiagnoses(object $old): ?string
    {
        $diagnoses = [];

        for ($i = 1; $i <= 4; $i++) {
            $pri = $old->{"pri{$i}"} ?? '';
            if ($pri && trim($pri) !== '') {
                $diagnoses[] = trim($pri);
            }
        }

        for ($i = 1; $i <= 4; $i++) {
            $pro = $old->{"pro{$i}"} ?? '';
            if ($pro && trim($pro) !== '') {
                $diagnoses[] = trim($pro);
            }
        }

        return $diagnoses ? implode("\n", array_unique($diagnoses)) : null;
    }

    private function saveOrphanedRecord(object $old, string $reason, ?string $notes = null): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        DB::table('mittag_orphaned_records')->updateOrInsert(
            [
                'entity_type' => 'ward_round',
                'old_id' => $old->id,
            ],
            [
                'old_identifier' => $old->folder_id,
                'reason' => $reason,
                'full_data' => json_encode([
                    'folder_id' => $old->folder_id,
                    'date' => $old->date,
                    'ward' => $old->ward,
                    'pc' => $old->pc,
                    'oe' => $old->oe,
                ]),
                'notes' => $notes,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function logMigration(object $old, ?int $newId, string $status, ?string $notes = null): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        DB::table('mittag_migration_logs')->updateOrInsert(
            [
                'entity_type' => 'ward_round',
                'old_id' => $old->id,
            ],
            [
                'new_id' => $newId,
                'old_identifier' => $old->folder_id,
                'new_identifier' => $newId,
                'status' => $status,
                'notes' => $notes ? mb_convert_encoding(substr($notes, 0, 500), 'UTF-8', 'UTF-8') : null,
                'old_data' => json_encode([
                    'folder_id' => $old->folder_id,
                    'date' => $old->date,
                    'ward' => $old->ward,
                    'request_id' => $old->request_id,
                ]),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
