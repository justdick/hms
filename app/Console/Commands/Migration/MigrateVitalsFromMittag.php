<?php

namespace App\Console\Commands\Migration;

use App\Models\Patient;
use App\Models\VitalSign;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateVitalsFromMittag extends Command
{
    protected $signature = 'migrate:vitals-from-mittag 
                            {--limit= : Limit number of records to migrate}
                            {--dry-run : Run without actually inserting data}
                            {--skip-existing : Skip vitals that already exist}
                            {--source=opd : Source table (opd or ipd)}';

    protected $description = 'Migrate vitals from Mittag old system to HMS';

    private int $migrated = 0;

    private int $skipped = 0;

    private int $failed = 0;

    private array $patientMap = [];

    private array $checkinMap = [];

    public function handle(): int
    {
        $source = $this->option('source');
        $tableName = $source === 'ipd' ? 'ipd_vitals' : 'opd_vitals';

        $this->info("Starting {$source} vitals migration from Mittag...");

        try {
            DB::connection('mittag_old')->getPdo();
            $this->info('✓ Connected to mittag_old database');
        } catch (\Exception $e) {
            $this->error('Cannot connect to mittag_old database: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Build mappings
        $this->buildPatientMap();
        $this->buildCheckinMap();

        $query = DB::connection('mittag_old')->table($tableName);

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $total = DB::connection('mittag_old')->table($tableName)->count();
        $this->info("Found {$total} vitals in Mittag {$tableName}");
        $this->info('Patient map size: '.count($this->patientMap));
        $this->info('Checkin map size: '.count($this->checkinMap));

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No data will be inserted');
        }

        $bar = $this->output->createProgressBar($this->option('limit') ? (int) $this->option('limit') : $total);
        $bar->start();

        $query->orderBy('id')->chunk(100, function ($vitals) use ($bar, $source) {
            foreach ($vitals as $oldVital) {
                $this->migrateVital($oldVital, $source);
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

    private function buildCheckinMap(): void
    {
        // Map folder_id + date to checkin id
        $logs = DB::table('mittag_migration_logs')
            ->where('entity_type', 'checkin')
            ->where('status', 'success')
            ->select('new_id', 'old_data')
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

    private function migrateVital(object $old, string $source): void
    {
        $entityType = $source === 'ipd' ? 'ipd_vital' : 'opd_vital';

        try {
            // Check if already migrated
            if ($this->option('skip-existing')) {
                $existingLog = DB::table('mittag_migration_logs')
                    ->where('entity_type', $entityType)
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
                $this->logMigration($old, null, $entityType, 'failed', "Patient not found: {$folderId}");
                $this->saveOrphanedRecord($old, $entityType, 'patient_not_found');
                $this->failed++;

                return;
            }

            // Find checkin by folder_id + date
            $date = $old->date;
            $key = "{$folderId}_{$date}";
            $checkinId = $this->checkinMap[$key] ?? null;

            if (! $checkinId) {
                // For IPD vitals, we might not have a checkin - create orphan record
                $this->logMigration($old, null, $entityType, 'failed', "Checkin not found for {$folderId} on {$date}");
                $this->saveOrphanedRecord($old, $entityType, 'checkin_not_found');
                $this->failed++;

                return;
            }

            if ($this->option('dry-run')) {
                $this->migrated++;

                return;
            }

            // Calculate BMI if height and weight are available
            $bmi = null;
            $weight = (float) $old->weight;
            $height = (float) $old->height;
            if ($weight > 0 && $height > 0) {
                // Height in cm, convert to meters for BMI
                $heightM = $height / 100;
                $bmi = round($weight / ($heightM * $heightM), 2);
            }

            // Determine recorded_at timestamp
            $recordedAt = $source === 'ipd' && isset($old->timestamp)
                ? Carbon::parse($old->timestamp)
                : Carbon::parse($old->date);

            // Build notes from extra fields
            $notes = $this->buildNotes($old, $source);

            $vitalData = [
                'patient_id' => $patientId,
                'patient_checkin_id' => $checkinId,
                'patient_admission_id' => null, // Will link later when admissions are migrated
                'recorded_by' => 1, // System user
                'blood_pressure_systolic' => $old->systolic > 0 ? $old->systolic : null,
                'blood_pressure_diastolic' => $old->diastolic > 0 ? $old->diastolic : null,
                'temperature' => (float) $old->temp > 0 ? $old->temp : null,
                'pulse_rate' => $old->pulse > 0 ? $old->pulse : null,
                'respiratory_rate' => $old->resp > 0 ? $old->resp : null,
                'weight' => $weight > 0 ? $weight : null,
                'height' => $height > 0 ? $height : null,
                'bmi' => $bmi,
                'oxygen_saturation' => $old->sat > 0 ? $old->sat : null,
                'notes' => $notes,
                'recorded_at' => $recordedAt,
                'created_at' => $recordedAt,
                'updated_at' => $recordedAt,
                'migrated_from_mittag' => true,
            ];

            $vital = VitalSign::create($vitalData);

            $this->logMigration($old, $vital->id, $entityType, 'success');
            $this->migrated++;

        } catch (\Exception $e) {
            $this->logMigration($old, null, $entityType, 'failed', $e->getMessage());
            $this->saveOrphanedRecord($old, $entityType, 'error', $e->getMessage());
            $this->failed++;
            Log::error('Vital migration failed', [
                'old_id' => $old->id,
                'folder_id' => $old->folder_id,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildNotes(object $old, string $source): ?string
    {
        $parts = [];

        // Blood sugar if recorded
        $sugar = (float) ($old->sugar ?? 0);
        if ($sugar > 0) {
            $parts[] = "Blood Sugar: {$sugar}";
        }

        // RDT result (OPD only)
        if ($source === 'opd' && isset($old->rdt) && trim($old->rdt) !== '') {
            $parts[] = 'RDT: '.trim($old->rdt);
        }

        // Ward info (IPD only)
        if ($source === 'ipd' && isset($old->ward) && $old->ward > 0) {
            $parts[] = "Ward ID: {$old->ward}";
        }

        if (empty($parts)) {
            return null;
        }

        return "Migrated from Mittag ({$source}). ".implode(', ', $parts);
    }

    private function saveOrphanedRecord(object $old, string $entityType, string $reason, ?string $notes = null): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        DB::table('mittag_orphaned_records')->updateOrInsert(
            [
                'entity_type' => $entityType,
                'old_id' => $old->id,
            ],
            [
                'old_identifier' => $old->folder_id,
                'reason' => $reason,
                'full_data' => json_encode((array) $old),
                'notes' => $notes,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function logMigration(object $old, ?int $newId, string $entityType, string $status, ?string $notes = null): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        DB::table('mittag_migration_logs')->updateOrInsert(
            [
                'entity_type' => $entityType,
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
                    'systolic' => $old->systolic,
                    'diastolic' => $old->diastolic,
                    'temp' => $old->temp,
                    'pulse' => $old->pulse,
                ]),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
