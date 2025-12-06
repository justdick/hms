<?php

namespace App\Console\Commands\Migration;

use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigratePatientsFromMittag extends Command
{
    protected $signature = 'migrate:patients-from-mittag 
                            {--limit= : Limit number of records to migrate}
                            {--dry-run : Run without actually inserting data}
                            {--skip-existing : Skip patients that already exist by folder_id}';

    protected $description = 'Migrate patients from Mittag old system to HMS';

    private int $migrated = 0;
    private int $skipped = 0;
    private int $failed = 0;

    public function handle(): int
    {
        $this->info('Starting patient migration from Mittag...');

        // Check connection
        try {
            DB::connection('mittag_old')->getPdo();
            $this->info('✓ Connected to mittag_old database');
        } catch (\Exception $e) {
            $this->error('Cannot connect to mittag_old database: ' . $e->getMessage());
            $this->line('');
            $this->line('Please ensure:');
            $this->line('1. Create database: CREATE DATABASE mittag_old;');
            $this->line('2. Import backup: mysql -u root mittag_old < backup_mittag.sql');
            $this->line('3. Add to .env: MITTAG_DB_DATABASE=mittag_old');
            return Command::FAILURE;
        }

        $query = DB::connection('mittag_old')->table('patients');
        
        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $total = DB::connection('mittag_old')->table('patients')->count();
        $this->info("Found {$total} patients in Mittag database");

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No data will be inserted');
        }

        $bar = $this->output->createProgressBar($this->option('limit') ? (int) $this->option('limit') : $total);
        $bar->start();

        $query->orderBy('id')->chunk(100, function ($patients) use ($bar) {
            foreach ($patients as $oldPatient) {
                $this->migratePatient($oldPatient);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Migration completed:");
        $this->line("  ✓ Migrated: {$this->migrated}");
        $this->line("  ⊘ Skipped:  {$this->skipped}");
        $this->line("  ✗ Failed:   {$this->failed}");

        return Command::SUCCESS;
    }

    private function migratePatient(object $old): void
    {
        try {
            // Check if already migrated
            $existingLog = DB::table('mittag_migration_logs')
                ->where('entity_type', 'patient')
                ->where('old_id', $old->id)
                ->first();

            if ($existingLog && $existingLog->status === 'success') {
                $this->skipped++;
                return;
            }

            // Check if patient exists by folder_id (old patient number)
            if ($this->option('skip-existing')) {
                $existing = Patient::where('patient_number', 'LIKE', '%' . $old->folder_id)
                    ->orWhere('national_id', $old->folder_id)
                    ->first();
                
                if ($existing) {
                    $this->logMigration($old, $existing->id, $existing->patient_number, 'skipped', 'Patient already exists');
                    $this->skipped++;
                    return;
                }
            }

            if ($this->option('dry-run')) {
                $this->migrated++;
                return;
            }

            // Map old data to new structure
            $patientData = $this->mapPatientData($old);

            $patient = Patient::create($patientData);

            $this->logMigration($old, $patient->id, $patient->patient_number, 'success');
            $this->migrated++;

        } catch (\Exception $e) {
            $this->logMigration($old, null, null, 'failed', $e->getMessage());
            $this->failed++;
            Log::error('Patient migration failed', [
                'old_id' => $old->id,
                'folder_id' => $old->folder_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function mapPatientData(object $old): array
    {
        // Parse name - old system has sname (surname), fname (first), mname (middle)
        $firstName = trim($old->fname) ?: 'Unknown';
        $lastName = trim($old->sname) ?: 'Unknown';
        
        // Map gender
        $gender = strtolower(trim($old->gender));
        $gender = match (true) {
            str_starts_with($gender, 'm') => 'male',
            str_starts_with($gender, 'f') => 'female',
            default => 'male',
        };

        // Parse date of birth
        $dob = null;
        if ($old->dob && $old->dob !== '0000-00-00') {
            try {
                $dob = Carbon::parse($old->dob)->format('Y-m-d');
            } catch (\Exception $e) {
                $dob = null;
            }
        }

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => $gender,
            'date_of_birth' => $dob,
            'phone_number' => $this->cleanPhone($old->phone),
            'address' => trim($old->address) ?: null,
            'emergency_contact_name' => trim($old->nok) ?: null,
            'emergency_contact_phone' => $this->cleanPhone($old->nokadd), // nokadd seems to be NOK address/phone
            'national_id' => $old->folder_id, // Store old folder_id as reference
            'status' => 'active',
            // Medical history from old system
            'past_medical_surgical_history' => trim($old->problem) ?: null,
        ];
    }

    private function cleanPhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }
        
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        return $phone ?: null;
    }

    private function logMigration(object $old, ?int $newId, ?string $newIdentifier, string $status, ?string $notes = null): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        DB::table('mittag_migration_logs')->updateOrInsert(
            [
                'entity_type' => 'patient',
                'old_id' => $old->id,
            ],
            [
                'new_id' => $newId,
                'old_identifier' => $old->folder_id,
                'new_identifier' => $newIdentifier,
                'status' => $status,
                'notes' => $notes,
                'old_data' => json_encode([
                    'sname' => $old->sname,
                    'fname' => $old->fname,
                    'mname' => $old->mname,
                    'dob' => $old->dob,
                    'gender' => $old->gender,
                    'sponsor' => $old->sponsor,
                    'memid' => $old->memid,
                ]),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
