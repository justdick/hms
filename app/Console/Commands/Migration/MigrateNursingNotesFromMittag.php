<?php

namespace App\Console\Commands\Migration;

use App\Models\NursingNote;
use App\Models\Patient;
use App\Models\PatientAdmission;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateNursingNotesFromMittag extends Command
{
    protected $signature = 'migrate:nursing-notes-from-mittag 
                            {--limit= : Limit number of records to migrate}
                            {--dry-run : Run without actually inserting data}
                            {--skip-existing : Skip notes that already exist}';

    protected $description = 'Migrate nursing notes from Mittag old system to HMS';

    private int $migrated = 0;

    private int $skipped = 0;

    private int $failed = 0;

    private array $patientMap = [];

    private array $admissionMap = [];

    public function handle(): int
    {
        $this->info('Starting nursing notes migration from Mittag...');

        try {
            DB::connection('mittag_old')->getPdo();
            $this->info('✓ Connected to mittag_old database');
        } catch (\Exception $e) {
            $this->error('Cannot connect to mittag_old database: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Build mappings
        $this->buildPatientMap();
        $this->buildAdmissionMap();

        $query = DB::connection('mittag_old')->table('nurses_notes');

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $total = DB::connection('mittag_old')->table('nurses_notes')->count();
        $this->info("Found {$total} nursing notes in Mittag");
        $this->info('Patient map size: '.count($this->patientMap));
        $this->info('Admission map size: '.count($this->admissionMap));

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No data will be inserted');
        }

        $bar = $this->output->createProgressBar($this->option('limit') ? (int) $this->option('limit') : $total);
        $bar->start();

        $query->orderBy('id')->chunk(500, function ($notes) use ($bar) {
            foreach ($notes as $oldNote) {
                $this->migrateNursingNote($oldNote);
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

    private function buildPatientMap(): void
    {
        $this->patientMap = Patient::pluck('id', 'patient_number')->toArray();
    }

    private function buildAdmissionMap(): void
    {
        // Build map of patient_id => array of admissions with date ranges
        $admissions = PatientAdmission::select('id', 'patient_id', 'admitted_at', 'discharged_at')
            ->orderBy('admitted_at')
            ->get();

        foreach ($admissions as $admission) {
            $patientId = $admission->patient_id;
            if (! isset($this->admissionMap[$patientId])) {
                $this->admissionMap[$patientId] = [];
            }
            $this->admissionMap[$patientId][] = [
                'id' => $admission->id,
                'admitted_at' => $admission->admitted_at,
                'discharged_at' => $admission->discharged_at,
            ];
        }
    }

    private function findAdmissionForNote(int $patientId, Carbon $noteDate): ?int
    {
        if (! isset($this->admissionMap[$patientId])) {
            return null;
        }

        foreach ($this->admissionMap[$patientId] as $admission) {
            $admittedAt = Carbon::parse($admission['admitted_at'])->startOfDay();
            $dischargedAt = $admission['discharged_at']
                ? Carbon::parse($admission['discharged_at'])->endOfDay()
                : now()->endOfDay();

            if ($noteDate->between($admittedAt, $dischargedAt)) {
                return $admission['id'];
            }
        }

        return null;
    }

    private function migrateNursingNote(object $old): void
    {
        try {
            // Check if already migrated
            if ($this->option('skip-existing')) {
                $existingLog = DB::table('mittag_migration_logs')
                    ->where('entity_type', 'nursing_note')
                    ->where('old_id', $old->id)
                    ->where('status', 'success')
                    ->first();

                if ($existingLog) {
                    $this->skipped++;

                    return;
                }
            }

            // Find patient
            $folderId = trim($old->folder_id);
            $patientId = $this->patientMap[$folderId] ?? null;

            if (! $patientId) {
                $this->logMigration($old, null, 'failed', "Patient not found: {$folderId}");
                $this->failed++;

                return;
            }

            // Validate date
            if (empty($old->date) || $old->date === '0000-00-00') {
                $this->logMigration($old, null, 'failed', "Invalid date: {$old->date}");
                $this->failed++;

                return;
            }

            $noteDate = Carbon::parse($old->date);
            if ($noteDate->year < 2000 || $noteDate->year > 2030) {
                $this->logMigration($old, null, 'failed', "Invalid date year: {$old->date}");
                $this->failed++;

                return;
            }

            // Find admission for this patient on this date
            $admissionId = $this->findAdmissionForNote($patientId, $noteDate);

            if (! $admissionId) {
                $this->logMigration($old, null, 'failed', "No admission found for patient {$folderId} on {$old->date}");
                $this->failed++;

                return;
            }

            if ($this->option('dry-run')) {
                $this->migrated++;

                return;
            }

            // Combine date and time
            $notedAt = Carbon::parse($old->date.' '.$old->time);

            // Clean HTML from note
            $noteText = strip_tags($old->note);
            $noteText = html_entity_decode($noteText);
            $noteText = trim($noteText);

            $nursingNoteData = [
                'patient_admission_id' => $admissionId,
                'nurse_id' => 1, // System user
                'type' => 'observation', // Valid enum: assessment, care, observation, incident, handover
                'note' => $noteText,
                'noted_at' => $notedAt,
                'created_at' => $notedAt,
                'updated_at' => $notedAt,
                'migrated_from_mittag' => true,
            ];

            $nursingNote = NursingNote::create($nursingNoteData);

            $this->logMigration($old, $nursingNote->id, 'success');
            $this->migrated++;

        } catch (\Exception $e) {
            $this->logMigration($old, null, 'failed', $e->getMessage());
            $this->failed++;
            Log::error('Nursing note migration failed', [
                'old_id' => $old->id,
                'folder_id' => $old->folder_id ?? null,
                'error' => $e->getMessage(),
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
                'entity_type' => 'nursing_note',
                'old_id' => $old->id,
            ],
            [
                'new_id' => $newId,
                'old_identifier' => $old->folder_id ?? null,
                'new_identifier' => $newId,
                'status' => $status,
                'notes' => $notes ? substr($notes, 0, 500) : null,
                'old_data' => json_encode([
                    'folder_id' => $old->folder_id,
                    'date' => $old->date,
                    'time' => $old->time,
                ]),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
