<?php

namespace App\Console\Commands\Migration;

use App\Models\Department;
use App\Models\Patient;
use App\Models\PatientCheckin;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateCheckinsFromMittag extends Command
{
    protected $signature = 'migrate:checkins-from-mittag 
                            {--limit= : Limit number of records to migrate}
                            {--dry-run : Run without actually inserting data}
                            {--skip-existing : Skip checkins that already exist}';

    protected $description = 'Migrate checkins from Mittag old system to HMS';

    private int $migrated = 0;

    private int $skipped = 0;

    private int $failed = 0;

    private array $departmentMap = [];

    private array $patientMap = [];

    public function handle(): int
    {
        $this->info('Starting checkin migration from Mittag...');

        try {
            DB::connection('mittag_old')->getPdo();
            $this->info('✓ Connected to mittag_old database');
        } catch (\Exception $e) {
            $this->error('Cannot connect to mittag_old database: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Build department mapping
        $this->buildDepartmentMap();

        // Build patient mapping (folder_id -> patient_id)
        $this->buildPatientMap();

        $query = DB::connection('mittag_old')->table('checkin');

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $total = DB::connection('mittag_old')->table('checkin')->count();
        $this->info("Found {$total} checkins in Mittag database");
        $this->info('Patient map size: '.count($this->patientMap));

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No data will be inserted');
        }

        $bar = $this->output->createProgressBar($this->option('limit') ? (int) $this->option('limit') : $total);
        $bar->start();

        $query->orderBy('id')->chunk(100, function ($checkins) use ($bar) {
            foreach ($checkins as $oldCheckin) {
                $this->migrateCheckin($oldCheckin);
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

    private function buildDepartmentMap(): void
    {
        // Map old clinic codes to new department IDs
        $departments = Department::pluck('id', 'code')->toArray();

        // Old system clinic codes mapping
        $this->departmentMap = [
            'OPDC' => $departments['OPDC'] ?? null,
            'ANC' => $departments['ANCP'] ?? null,
            'ZOOM' => $departments['ZOOM'] ?? null,
            'PAED' => $departments['PAED'] ?? null,
            'MEDI' => $departments['MEDI'] ?? null,
            'DENT' => $departments['DENT'] ?? null,
            'ENTH' => $departments['ENTH'] ?? null,
            'OPTH' => $departments['OPTH'] ?? null,
        ];

        // Default to OPDC if not mapped
        $this->departmentMap['default'] = $departments['OPDC'] ?? 1;
    }

    private function buildPatientMap(): void
    {
        // Map folder_id (now patient_number) to patient_id
        $this->patientMap = Patient::pluck('id', 'patient_number')->toArray();
    }

    private function migrateCheckin(object $old): void
    {
        try {
            // Check if already migrated
            $existingLog = DB::table('mittag_migration_logs')
                ->where('entity_type', 'checkin')
                ->where('old_id', $old->id)
                ->first();

            if ($existingLog && $existingLog->status === 'success') {
                $this->skipped++;

                return;
            }

            // Find patient by folder_id (try exact match first, then trimmed)
            $folderId = $old->folder_id;
            $patientId = $this->patientMap[$folderId] ?? null;

            // Try trimmed version if exact match fails
            if (! $patientId) {
                $trimmedFolderId = trim($folderId);
                $patientId = $this->patientMap[$trimmedFolderId] ?? null;
            }

            if (! $patientId) {
                $this->logMigration($old, null, null, 'failed', "Patient not found: {$old->folder_id}");
                $this->saveOrphanedRecord($old, 'patient_not_found');
                $this->failed++;

                return;
            }

            // Get department
            $departmentId = $this->departmentMap[$old->clinic] ?? $this->departmentMap['default'];

            if ($this->option('dry-run')) {
                $this->migrated++;

                return;
            }

            // Generate unique claim_check_code - append old ID if ccc exists to avoid duplicates
            $claimCheckCode = null;
            if ($old->ccc && trim($old->ccc) !== '') {
                // Check if this ccc already exists in our system
                $existingCcc = PatientCheckin::where('claim_check_code', $old->ccc)->exists();
                $claimCheckCode = $existingCcc ? "{$old->ccc}-{$old->id}" : $old->ccc;
            }

            $checkinData = [
                'patient_id' => $patientId,
                'department_id' => $departmentId,
                'checked_in_by' => 1, // System user
                'checked_in_at' => Carbon::parse($old->date)->startOfDay(),
                'status' => $this->mapStatus($old),
                'claim_check_code' => $claimCheckCode,
                'notes' => "Migrated from Mittag. Original clinic: {$old->clinic}, Sponsor: {$old->sponsor}",
                'created_at' => Carbon::parse($old->date),
                'updated_at' => Carbon::parse($old->date),
                'migrated_from_mittag' => true,
            ];

            // Set timestamps based on status
            if ($old->seen) {
                $checkinData['vitals_taken_at'] = Carbon::parse($old->date)->addMinutes(15);
                $checkinData['consultation_started_at'] = Carbon::parse($old->date)->addMinutes(30);
                $checkinData['consultation_completed_at'] = Carbon::parse($old->date)->addHours(1);
            }

            $checkin = PatientCheckin::create($checkinData);

            $this->logMigration($old, $checkin->id, $checkin->claim_check_code, 'success');
            $this->migrated++;

        } catch (\Exception $e) {
            $this->logMigration($old, null, null, 'failed', $e->getMessage());
            $this->saveOrphanedRecord($old, 'error', $e->getMessage());
            $this->failed++;
            Log::error('Checkin migration failed', [
                'old_id' => $old->id,
                'folder_id' => $old->folder_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function saveOrphanedRecord(object $old, string $reason, ?string $notes = null): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        DB::table('mittag_orphaned_records')->updateOrInsert(
            [
                'entity_type' => 'checkin',
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

    private function mapStatus(object $old): string
    {
        if ($old->admit) {
            return 'admitted';
        }
        if ($old->seen) {
            return 'completed';
        }

        // Patient was never seen - mark as cancelled (historical no-show)
        return 'cancelled';
    }

    private function logMigration(object $old, ?int $newId, ?string $newIdentifier, string $status, ?string $notes = null): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        DB::table('mittag_migration_logs')->updateOrInsert(
            [
                'entity_type' => 'checkin',
                'old_id' => $old->id,
            ],
            [
                'new_id' => $newId,
                'old_identifier' => $old->folder_id,
                'new_identifier' => $newIdentifier,
                'status' => $status,
                'notes' => $notes,
                'old_data' => json_encode([
                    'folder_id' => $old->folder_id,
                    'clinic' => $old->clinic,
                    'date' => $old->date,
                    'sponsor' => $old->sponsor,
                    'seen' => $old->seen,
                    'admit' => $old->admit,
                ]),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
