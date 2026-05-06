<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillIpdVitalsFromMittag extends Command
{
    protected $signature = 'backfill:ipd-vitals-from-mittag
                            {--dry-run : Show what would be inserted without making changes}
                            {--limit= : Limit number of records to process}';

    protected $description = 'Backfill failed IPD vitals from Mittag by matching to admissions instead of checkins';

    private int $inserted = 0;

    private int $skipped = 0;

    private int $failed = 0;

    private array $patientMap = [];

    private array $admissionMap = [];

    public function handle(): int
    {
        $this->info('Backfilling failed IPD vitals from Mittag...');

        try {
            DB::connection('mittag_old')->getPdo();
            $this->info('✓ Connected to mittag_old database');
        } catch (\Exception $e) {
            $this->error('Cannot connect to mittag_old database: ' . $e->getMessage());

            return Command::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No data will be inserted');
        }

        $this->buildPatientMap();
        $this->buildAdmissionMap();

        $this->info('Patient map size: ' . count($this->patientMap));
        $this->info('Admission map size: ' . count($this->admissionMap));

        // Get the old_ids of failed IPD vitals from migration logs
        $query = DB::table('mittag_migration_logs')
            ->where('entity_type', 'ipd_vital')
            ->where('status', 'failed')
            ->select('old_id');

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $total = (clone $query)->count();
        $this->info("Found {$total} failed IPD vitals to retry");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->orderBy('old_id')->chunk(200, function ($logs) use ($bar) {
            $oldIds = $logs->pluck('old_id')->toArray();

            // Batch fetch from Mittag
            $mittagVitals = DB::connection('mittag_old')
                ->table('ipd_vitals')
                ->whereIn('id', $oldIds)
                ->get();

            foreach ($mittagVitals as $old) {
                $this->processVital($old);
                $bar->advance();
            }

            // Advance for any IDs not found in mittag
            $found = $mittagVitals->count();
            $notFound = count($oldIds) - $found;
            for ($i = 0; $i < $notFound; $i++) {
                $this->skipped++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info('Backfill completed:');
        $this->line("  ✓ Inserted:  {$this->inserted}");
        $this->line("  ⊘ Skipped:   {$this->skipped}");
        $this->line("  ✗ Failed:    {$this->failed}");

        return Command::SUCCESS;
    }

    private function processVital(object $old): void
    {
        $folderId = trim($old->folder_id);

        // Find patient
        $patientId = $this->patientMap[$folderId] ?? null;
        if (! $patientId) {
            $this->failed++;

            return;
        }

        // Find admission that covers this vital's date
        $admissionId = $this->findAdmission($folderId, $old->date);
        if (! $admissionId) {
            $this->skipped++;

            return;
        }

        // Also find a checkin for this patient on this date (best effort)
        $checkinId = DB::table('patient_checkins')
            ->where('patient_id', $patientId)
            ->where('service_date', $old->date)
            ->value('id');

        // If no checkin on exact date, find the admission's checkin
        if (! $checkinId) {
            $checkinId = DB::table('patient_checkins')
                ->where('patient_id', $patientId)
                ->where('status', 'admitted')
                ->whereDate('checked_in_at', '<=', $old->date)
                ->orderByDesc('checked_in_at')
                ->value('id');
        }

        if (! $checkinId) {
            // Last resort: find any checkin for this patient closest to this date
            $checkinId = DB::table('patient_checkins')
                ->where('patient_id', $patientId)
                ->whereDate('checked_in_at', '<=', $old->date)
                ->orderByDesc('checked_in_at')
                ->value('id');
        }

        if (! $checkinId) {
            $this->failed++;

            return;
        }

        $recordedAt = isset($old->timestamp) && $old->timestamp !== '0000-00-00 00:00:00'
            ? Carbon::parse($old->timestamp)
            : Carbon::parse($old->date);

        $bmi = null;
        if ($old->weight > 0 && $old->height > 0) {
            $heightInMeters = $old->height / 100;
            $bmi = round($old->weight / ($heightInMeters * $heightInMeters), 2);
        }

        $notes = $this->buildNotes($old);

        if (! $this->option('dry-run')) {
            try {
                DB::table('vital_signs')->insert([
                    'migrated_from_mittag' => true,
                    'patient_id' => $patientId,
                    'patient_checkin_id' => $checkinId,
                    'patient_admission_id' => $admissionId,
                    'recorded_by' => 1,
                    'blood_pressure_systolic' => $old->systolic > 0 ? $old->systolic : null,
                    'blood_pressure_diastolic' => $old->diastolic > 0 ? $old->diastolic : null,
                    'temperature' => $old->temp > 0 ? $old->temp : null,
                    'pulse_rate' => $old->pulse > 0 ? $old->pulse : null,
                    'respiratory_rate' => $old->resp > 0 ? $old->resp : null,
                    'weight' => $old->weight > 0 ? $old->weight : null,
                    'height' => $old->height > 0 ? $old->height : null,
                    'bmi' => $bmi,
                    'oxygen_saturation' => $old->sat > 0 ? $old->sat : null,
                    'blood_sugar' => $old->sugar > 0 ? $old->sugar : null,
                    'notes' => $notes,
                    'recorded_at' => $recordedAt,
                    'created_at' => $recordedAt,
                    'updated_at' => $recordedAt,
                ]);
            } catch (\Exception $e) {
                // Out-of-range values — store raw values in notes for manual review
                $outOfRange = "Original values - BP: {$old->systolic}/{$old->diastolic}, Temp: {$old->temp}, Pulse: {$old->pulse}, RR: {$old->resp}, Wt: {$old->weight}, Ht: {$old->height}, SpO2: {$old->sat}, Sugar: {$old->sugar}";
                $combinedNotes = $notes ? "{$notes}\n{$outOfRange}" : $outOfRange;

                try {
                    DB::table('vital_signs')->insert([
                        'migrated_from_mittag' => true,
                        'patient_id' => $patientId,
                        'patient_checkin_id' => $checkinId,
                        'patient_admission_id' => $admissionId,
                        'recorded_by' => 1,
                        'blood_pressure_systolic' => null,
                        'blood_pressure_diastolic' => null,
                        'temperature' => null,
                        'pulse_rate' => null,
                        'respiratory_rate' => null,
                        'weight' => null,
                        'height' => null,
                        'bmi' => null,
                        'oxygen_saturation' => null,
                        'blood_sugar' => null,
                        'notes' => $combinedNotes,
                        'recorded_at' => $recordedAt,
                        'created_at' => $recordedAt,
                        'updated_at' => $recordedAt,
                    ]);
                } catch (\Exception $e2) {
                    $this->failed++;

                    return;
                }
            }

            // Update migration log to success
            DB::table('mittag_migration_logs')
                ->where('entity_type', 'ipd_vital')
                ->where('old_id', $old->id)
                ->update([
                    'status' => 'success',
                    'notes' => 'Backfilled via admission match',
                    'updated_at' => now(),
                ]);
        }

        $this->inserted++;
    }

    private function findAdmission(string $folderId, string $date): ?int
    {
        $admissions = $this->admissionMap[$folderId] ?? [];

        foreach ($admissions as $admission) {
            // Use a buffer: 1 day before admission, 2 days after discharge
            // This handles vitals taken on admission day before formal admission time,
            // and vitals taken morning after discharge date was recorded
            $admittedAt = Carbon::parse($admission['admitted_at'])->subDay()->format('Y-m-d');
            $dischargedAt = $admission['discharged_at']
                ? Carbon::parse($admission['discharged_at'])->addDays(2)->format('Y-m-d')
                : now()->format('Y-m-d');

            if ($date >= $admittedAt && $date <= $dischargedAt) {
                return $admission['id'];
            }
        }

        return null;
    }

    private function buildPatientMap(): void
    {
        $logs = DB::table('mittag_migration_logs')
            ->where('entity_type', 'patient')
            ->where('status', 'success')
            ->select('old_identifier', 'new_id')
            ->get();

        foreach ($logs as $log) {
            if ($log->old_identifier) {
                $this->patientMap[trim($log->old_identifier)] = $log->new_id;
            }
        }
    }

    private function buildAdmissionMap(): void
    {
        // Build a map of folder_id => [admissions with date ranges]
        $admissions = DB::table('patient_admissions')
            ->where('patient_admissions.migrated_from_mittag', true)
            ->join('patients', 'patient_admissions.patient_id', '=', 'patients.id')
            ->select(
                'patient_admissions.id',
                'patients.patient_number',
                'patient_admissions.admitted_at',
                'patient_admissions.discharged_at'
            )
            ->get();

        foreach ($admissions as $admission) {
            $folderId = trim($admission->patient_number);
            if (! isset($this->admissionMap[$folderId])) {
                $this->admissionMap[$folderId] = [];
            }
            $this->admissionMap[$folderId][] = [
                'id' => $admission->id,
                'admitted_at' => Carbon::parse($admission->admitted_at)->format('Y-m-d'),
                'discharged_at' => $admission->discharged_at
                    ? Carbon::parse($admission->discharged_at)->format('Y-m-d')
                    : null,
            ];
        }
    }

    private function buildNotes(object $old): ?string
    {
        $parts = [];

        if (isset($old->ward) && $old->ward > 0) {
            $parts[] = "Ward ID: {$old->ward}";
        }

        return $parts ? implode("\n", $parts) : null;
    }
}
