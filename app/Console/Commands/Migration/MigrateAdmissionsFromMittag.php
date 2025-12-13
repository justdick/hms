<?php

namespace App\Console\Commands\Migration;

use App\Models\Patient;
use App\Models\PatientAdmission;
use App\Models\PatientCheckin;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateAdmissionsFromMittag extends Command
{
    protected $signature = 'migrate:admissions-from-mittag 
                            {--limit= : Limit number of records to migrate}
                            {--dry-run : Run without actually inserting data}
                            {--skip-existing : Skip admissions that already exist}';

    protected $description = 'Migrate admissions from Mittag old system to HMS';

    private int $migrated = 0;

    private int $skipped = 0;

    private int $failed = 0;

    private array $patientMap = [];

    private array $checkinMap = [];

    private array $wardMap = [];

    public function handle(): int
    {
        $this->info('Starting admission migration from Mittag...');

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
        $this->buildWardMap();

        $query = DB::connection('mittag_old')->table('ipd_register');

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $total = DB::connection('mittag_old')->table('ipd_register')->count();
        $this->info("Found {$total} admissions in Mittag ipd_register");
        $this->info('Patient map size: '.count($this->patientMap));
        $this->info('Checkin map size: '.count($this->checkinMap));

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No data will be inserted');
        }

        $bar = $this->output->createProgressBar($this->option('limit') ? (int) $this->option('limit') : $total);
        $bar->start();

        $query->orderBy('id')->chunk(100, function ($admissions) use ($bar) {
            foreach ($admissions as $oldAdmission) {
                $this->migrateAdmission($oldAdmission);
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

    private function buildWardMap(): void
    {
        // Map old ward IDs to new ward IDs
        // Wards are migrated with same IDs from Mittag, so direct mapping works
        // Old system: 1 (Male), 2 (Female), 3 (Paediatric), 4 (Maternity), 9 (Emergency/Same Day)
        $existingWards = DB::table('wards')->pluck('id')->toArray();

        // Direct mapping - wards migrated with same IDs
        foreach ([1, 2, 3, 4, 9] as $wardId) {
            if (in_array($wardId, $existingWards)) {
                $this->wardMap[$wardId] = $wardId;
            } else {
                // Fallback to first available ward if specific ward doesn't exist
                $this->wardMap[$wardId] = $existingWards[0] ?? 1;
            }
        }
    }

    private function migrateAdmission(object $old): void
    {
        try {
            // Check if already migrated
            if ($this->option('skip-existing')) {
                $existingLog = DB::table('mittag_migration_logs')
                    ->where('entity_type', 'admission')
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
                $this->logMigration($old, null, null, 'failed', "Patient not found: {$folderId}");
                $this->saveOrphanedRecord($old, 'patient_not_found');
                $this->failed++;

                return;
            }

            // Find checkin by folder_id + admission date
            $key = "{$folderId}_{$old->date_admitted}";
            $checkinId = $this->checkinMap[$key] ?? null;

            // Get consultation from checkin if available
            $consultationId = null;
            if ($checkinId) {
                $checkin = PatientCheckin::with('consultation')->find($checkinId);
                $consultationId = $checkin?->consultation?->id;
            }

            if ($this->option('dry-run')) {
                $this->migrated++;

                return;
            }

            // Map ward
            $wardId = $this->wardMap[$old->ward] ?? 1;

            // Map status/outcome
            $status = $this->mapStatus($old->outcome);

            // Generate admission number
            $admissionNumber = $this->generateAdmissionNumber($old);

            $admittedAt = Carbon::parse($old->date_admitted);
            $dischargedAt = null;
            if ($old->date_discharged && $old->date_discharged !== '0000-00-00') {
                $dischargedAt = Carbon::parse($old->date_discharged);
            }

            $admissionData = [
                'admission_number' => $admissionNumber,
                'patient_id' => $patientId,
                'consultation_id' => $consultationId,
                'ward_id' => $wardId,
                'bed_id' => null, // No bed mapping from old system
                'status' => $status,
                'admission_reason' => "Migrated from Mittag. Original ward: {$old->ward}",
                'admission_notes' => $checkinId ? null : "No matching checkin found for admission date {$old->date_admitted}",
                'admitted_at' => $admittedAt,
                'discharged_at' => $dischargedAt,
                'discharge_notes' => $old->outcome ? "Outcome: {$old->outcome}" : null,
                'discharged_by_id' => $dischargedAt ? 1 : null,
                'is_overflow_patient' => false,
                'created_at' => $admittedAt,
                'updated_at' => $dischargedAt ?? $admittedAt,
                'migrated_from_mittag' => true,
            ];

            $admission = PatientAdmission::create($admissionData);

            // Log success immediately after admission creation
            $this->logMigration($old, $admission->id, $admission->admission_number, 'success');
            $this->migrated++;

            // Link orphaned IPD vitals to this admission (separate try-catch so it doesn't affect admission status)
            try {
                $this->linkOrphanedVitals($folderId, $old->date_admitted, $old->date_discharged, $admission->id, $checkinId);
            } catch (\Exception $e) {
                Log::warning('Failed to link orphaned vitals to admission', [
                    'admission_id' => $admission->id,
                    'folder_id' => $folderId,
                    'error' => $e->getMessage(),
                ]);
            }

        } catch (\Exception $e) {
            $this->logMigration($old, null, null, 'failed', $e->getMessage());
            $this->saveOrphanedRecord($old, 'error', $e->getMessage());
            $this->failed++;
            Log::error('Admission migration failed', [
                'old_id' => $old->id,
                'folder_id' => $old->folder_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function generateAdmissionNumber(object $old): string
    {
        $year = Carbon::parse($old->date_admitted)->format('Y');
        $sequence = str_pad($old->id, 6, '0', STR_PAD_LEFT);

        return "ADM{$year}{$sequence}";
    }

    private function mapStatus(?string $outcome): string
    {
        return match (strtolower(trim($outcome ?? ''))) {
            'discharged', '' => 'discharged',
            'referred' => 'transferred',
            'expired' => 'deceased',
            'absconded' => 'discharged', // Map absconded to discharged with notes
            default => 'discharged',
        };
    }

    private function linkOrphanedVitals(string $folderId, string $admitDate, string $dischargeDate, int $admissionId, ?int $checkinId): void
    {
        // Find orphaned IPD vitals for this patient during admission period
        $orphanedVitals = DB::table('mittag_orphaned_records')
            ->where('entity_type', 'ipd_vital')
            ->where('old_identifier', $folderId)
            ->get();

        $linkedCount = 0;
        foreach ($orphanedVitals as $orphan) {
            $vitalData = json_decode($orphan->full_data, true);
            if (! $vitalData) {
                continue;
            }

            $vitalDate = $vitalData['date'] ?? null;
            if (! $vitalDate) {
                continue;
            }

            // Check if vital date is within admission period
            if ($vitalDate >= $admitDate && ($dischargeDate === '0000-00-00' || $vitalDate <= $dischargeDate)) {
                // Create the vital sign now
                $this->createVitalFromOrphan($vitalData, $admissionId, $checkinId);
                $linkedCount++;

                // Mark orphan as processed
                DB::table('mittag_orphaned_records')
                    ->where('id', $orphan->id)
                    ->update([
                        'notes' => "Linked to admission {$admissionId}",
                        'updated_at' => now(),
                    ]);
            }
        }

        if ($linkedCount > 0) {
            Log::info("Linked {$linkedCount} orphaned IPD vitals to admission {$admissionId}");
        }
    }

    private function createVitalFromOrphan(array $old, int $admissionId, ?int $checkinId): void
    {
        $patientId = $this->patientMap[trim($old['folder_id'])] ?? null;
        if (! $patientId || ! $checkinId) {
            return;
        }

        $weight = (float) ($old['weight'] ?? 0);
        $height = (float) ($old['height'] ?? 0);
        $bmi = null;
        if ($weight > 0 && $height > 0) {
            $heightM = $height / 100;
            $bmi = round($weight / ($heightM * $heightM), 2);
        }

        // Validate and parse timestamp - skip if invalid
        $recordedAt = null;
        try {
            $timestamp = $old['timestamp'] ?? $old['date'] ?? null;
            if ($timestamp && $timestamp !== '0000-00-00' && $timestamp !== '0000-00-00 00:00:00' && ! str_starts_with($timestamp, '-')) {
                $recordedAt = Carbon::parse($timestamp);
            } else {
                // Use admission date as fallback
                $recordedAt = Carbon::parse($old['date'] ?? now());
            }
        } catch (\Exception $e) {
            // Use current date as last resort
            $recordedAt = now();
        }

        DB::table('vital_signs')->insert([
            'patient_id' => $patientId,
            'patient_checkin_id' => $checkinId,
            'patient_admission_id' => $admissionId,
            'recorded_by' => 1,
            'blood_pressure_systolic' => ($old['systolic'] ?? 0) > 0 ? $old['systolic'] : null,
            'blood_pressure_diastolic' => ($old['diastolic'] ?? 0) > 0 ? $old['diastolic'] : null,
            'temperature' => ((float) ($old['temp'] ?? 0)) > 0 ? $old['temp'] : null,
            'pulse_rate' => ($old['pulse'] ?? 0) > 0 ? $old['pulse'] : null,
            'respiratory_rate' => ($old['resp'] ?? 0) > 0 ? $old['resp'] : null,
            'weight' => $weight > 0 ? $weight : null,
            'height' => $height > 0 ? $height : null,
            'bmi' => $bmi,
            'oxygen_saturation' => ($old['sat'] ?? 0) > 0 ? $old['sat'] : null,
            'notes' => 'Migrated from Mittag (ipd). Linked via admission.',
            'recorded_at' => $recordedAt,
            'created_at' => $recordedAt,
            'updated_at' => $recordedAt,
            'migrated_from_mittag' => true,
        ]);
    }

    private function saveOrphanedRecord(object $old, string $reason, ?string $notes = null): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        DB::table('mittag_orphaned_records')->updateOrInsert(
            [
                'entity_type' => 'admission',
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

    private function logMigration(object $old, ?int $newId, ?string $newIdentifier, string $status, ?string $notes = null): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        DB::table('mittag_migration_logs')->updateOrInsert(
            [
                'entity_type' => 'admission',
                'old_id' => $old->id,
            ],
            [
                'new_id' => $newId,
                'old_identifier' => $old->folder_id,
                'new_identifier' => $newIdentifier,
                'status' => $status,
                'notes' => $notes ? substr($notes, 0, 500) : null,
                'old_data' => json_encode([
                    'folder_id' => $old->folder_id,
                    'ward' => $old->ward,
                    'date_admitted' => $old->date_admitted,
                    'date_discharged' => $old->date_discharged,
                    'outcome' => $old->outcome,
                ]),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
