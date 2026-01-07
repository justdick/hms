<?php

namespace App\Console\Commands;

use App\Models\Consultation;
use App\Models\ConsultationProcedure;
use App\Models\MinorProcedureType;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateOperationNotesFromMittag extends Command
{
    protected $signature = 'migrate:operation-notes-from-mittag
                            {--dry-run : Show what would be migrated without actually migrating}
                            {--limit= : Limit the number of records to migrate}
                            {--day-window=30 : Maximum days after operation date to look for checkin}';

    protected $description = 'Migrate operation/theatre notes from Mittag to consultation_procedures';

    private array $anaesthesiaMap = [
        'spinal anaesthesia' => 'spinal',
        'spinal' => 'spinal',
        'local anaesthesia' => 'local',
        'local' => 'local',
        'general anaesthesia' => 'general',
        'general' => 'general',
        'regional anaesthesia' => 'regional',
        'regional' => 'regional',
        'sedation' => 'sedation',
    ];

    private int $migrated = 0;

    private int $skipped = 0;

    private int $failed = 0;

    public function handle(): int
    {
        $this->info('Starting operation notes migration from Mittag...');

        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be inserted');
        }

        // Get operation notes from Mittag
        $query = DB::connection('mittag_old')
            ->table('operation_notes')
            ->orderBy('id');

        if ($limit) {
            $query->limit((int) $limit);
        }

        $operationNotes = $query->get();
        $total = $operationNotes->count();

        $this->info("Found {$total} operation notes to migrate");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($operationNotes as $note) {
            $this->processOperationNote($note, $dryRun);
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

    private function processOperationNote(object $note, bool $dryRun): void
    {
        // Check if already migrated
        $existing = DB::table('mittag_migration_logs')
            ->where('entity_type', 'operation_note')
            ->where('old_id', $note->id)
            ->where('status', 'success')
            ->exists();

        if ($existing) {
            $this->skipped++;

            return;
        }

        try {
            // Find the consultation for this operation note
            $consultation = $this->findConsultation($note);

            if (! $consultation) {
                $this->logMigration($note->id, null, 'failed', 'No matching consultation found');
                $this->failed++;

                return;
            }

            // Find the procedure type
            $procedureType = MinorProcedureType::where('code', $note->main_procedure)->first();

            if (! $procedureType) {
                $this->logMigration($note->id, null, 'failed', "Procedure type not found: {$note->main_procedure}");
                $this->failed++;

                return;
            }

            // Find the doctor
            $doctor = $this->findDoctor($note->surgeon);

            if (! $doctor) {
                // Use a default doctor (first admin or doctor)
                $doctor = User::role(['admin', 'doctor'])->first();
            }

            if (! $doctor) {
                $this->logMigration($note->id, null, 'failed', 'No doctor found');
                $this->failed++;

                return;
            }

            // Map anaesthesia type
            $anaesthesiaType = $this->mapAnaesthesiaType($note->anaesthesia);

            // Strip HTML from text fields
            $procedureSteps = $this->stripHtml($note->steps);
            $findings = $this->stripHtml($note->findings);
            $plan = $this->stripHtml($note->plan);
            $comments = $this->stripHtml($note->other);

            if ($dryRun) {
                $this->migrated++;

                return;
            }

            // Create the consultation procedure
            $procedure = ConsultationProcedure::create([
                'consultation_id' => $consultation->id,
                'doctor_id' => $doctor->id,
                'minor_procedure_type_id' => $procedureType->id,
                'indication' => null,
                'assistant' => $note->assistant ?: null,
                'anaesthetist' => $note->anaesthetist ?: null,
                'anaesthesia_type' => $anaesthesiaType,
                'estimated_gestational_age' => null,
                'parity' => null,
                'procedure_subtype' => $note->type ?: null,
                'procedure_steps' => $procedureSteps,
                'template_selections' => null,
                'findings' => $findings,
                'plan' => $plan,
                'comments' => $comments,
                'performed_at' => $note->date.' '.date('H:i:s', strtotime($note->timestamp)),
            ]);

            $this->logMigration($note->id, $procedure->id, 'success');
            $this->migrated++;

        } catch (\Exception $e) {
            $this->logMigration($note->id, null, 'failed', $e->getMessage());
            $this->failed++;
        }
    }

    private function findConsultation(object $note): ?Consultation
    {
        $dayWindow = (int) $this->option('day-window');

        // First, find the closest checkin for this patient before or on the operation date
        $checkin = DB::connection('mittag_old')
            ->table('checkin')
            ->where('folder_id', $note->folder_id)
            ->where('date', '<=', $note->date)
            ->orderBy('date', 'desc')
            ->first();

        // If no checkin before, try to find one after (within day-window days)
        if (! $checkin) {
            $checkin = DB::connection('mittag_old')
                ->table('checkin')
                ->where('folder_id', $note->folder_id)
                ->where('date', '>', $note->date)
                ->whereRaw('DATEDIFF(date, ?) <= ?', [$note->date, $dayWindow])
                ->orderBy('date', 'asc')
                ->first();
        }

        if (! $checkin) {
            return null;
        }

        // Find the migrated checkin in our system
        $migrationLog = DB::table('mittag_migration_logs')
            ->where('entity_type', 'checkin')
            ->where('old_id', $checkin->id)
            ->where('status', 'success')
            ->first();

        if (! $migrationLog) {
            return null;
        }

        // Find the consultation for this checkin
        return Consultation::where('patient_checkin_id', $migrationLog->new_id)->first();
    }

    private function findDoctor(int $staffId): ?User
    {
        // Check migration logs for staff
        $migrationLog = DB::table('mittag_migration_logs')
            ->where('entity_type', 'staff')
            ->where('old_id', $staffId)
            ->where('status', 'success')
            ->first();

        if ($migrationLog) {
            return User::find($migrationLog->new_id);
        }

        return null;
    }

    private function mapAnaesthesiaType(?string $anaesthesia): ?string
    {
        if (! $anaesthesia) {
            return null;
        }

        $normalized = strtolower(trim($anaesthesia));

        return $this->anaesthesiaMap[$normalized] ?? null;
    }

    private function stripHtml(?string $html): ?string
    {
        if (! $html) {
            return null;
        }

        // Decode HTML entities
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Replace <br>, <p>, <div> with newlines
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/p>/i', "\n", $text);
        $text = preg_replace('/<\/div>/i', "\n", $text);

        // Strip remaining HTML tags
        $text = strip_tags($text);

        // Clean up multiple newlines and whitespace
        $text = preg_replace('/\n\s*\n/', "\n\n", $text);
        $text = trim($text);

        return $text ?: null;
    }

    private function logMigration(int $oldId, ?int $newId, string $status, ?string $notes = null): void
    {
        DB::table('mittag_migration_logs')->insert([
            'entity_type' => 'operation_note',
            'old_id' => $oldId,
            'new_id' => $newId,
            'old_identifier' => null,
            'new_identifier' => null,
            'status' => $status,
            'notes' => $notes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
