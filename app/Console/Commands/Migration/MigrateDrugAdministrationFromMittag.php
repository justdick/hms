<?php

namespace App\Console\Commands\Migration;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateDrugAdministrationFromMittag extends Command
{
    protected $signature = 'migrate:drug-administration-from-mittag
                            {--dry-run : Show what would be migrated without making changes}
                            {--limit= : Limit number of records to process}';

    protected $description = 'Migrate drug administration (MAR) records from Mittag to medication_administrations';

    private int $migrated = 0;

    private int $skipped = 0;

    private int $failed = 0;

    private array $patientMap = [];

    private array $admissionMap = [];

    private array $prescriptionCache = [];

    public function handle(): int
    {
        $this->info('Migrating drug administration records from Mittag...');

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

        $query = DB::connection('mittag_old')->table('drug_administration');

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $total = DB::connection('mittag_old')->table('drug_administration')->count();
        $this->info("Found {$total} drug administration records in Mittag");

        $bar = $this->output->createProgressBar($this->option('limit') ? (int) $this->option('limit') : $total);
        $bar->start();

        $query->orderBy('id')->chunk(200, function ($records) use ($bar) {
            foreach ($records as $old) {
                $this->processRecord($old);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info('Migration completed:');
        $this->line("  ✓ Migrated:  {$this->migrated}");
        $this->line("  ⊘ Skipped:   {$this->skipped}");
        $this->line("  ✗ Failed:    {$this->failed}");

        return Command::SUCCESS;
    }

    private function processRecord(object $old): void
    {
        $folderId = trim($old->folder_id);

        // Find patient
        $patientId = $this->patientMap[$folderId] ?? null;
        if (! $patientId) {
            $this->failed++;

            return;
        }

        // Find admission covering this date
        $admissionId = $this->findAdmission($folderId, $old->date);
        if (! $admissionId) {
            $this->skipped++;

            return;
        }

        // Find matching prescription for this patient + drug + admission period
        $prescriptionId = $this->findPrescription($patientId, $old->prescription, $old->date, $admissionId);
        if (! $prescriptionId) {
            $this->skipped++;

            return;
        }

        // Parse administered_at from date + time
        $administeredAt = $this->parseDateTime($old->date, $old->time);

        if (! $this->option('dry-run')) {
            try {
                DB::table('medication_administrations')->insert([
                    'prescription_id' => $prescriptionId,
                    'patient_admission_id' => $admissionId,
                    'administered_by_id' => 1, // System user
                    'administered_at' => $administeredAt,
                    'status' => 'given',
                    'dosage_given' => $this->cleanText($old->dose),
                    'route' => $this->cleanText($old->route),
                    'notes' => null,
                    'created_at' => $administeredAt,
                    'updated_at' => $administeredAt,
                ]);
            } catch (\Exception $e) {
                $this->failed++;

                return;
            }
        }

        $this->migrated++;
    }

    private function findAdmission(string $folderId, string $date): ?int
    {
        $admissions = $this->admissionMap[$folderId] ?? [];

        foreach ($admissions as $admission) {
            // Buffer: 1 day before, 2 days after discharge
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

    private function findPrescription(int $patientId, string $prescriptionText, string $date, int $admissionId): ?int
    {
        // Extract drug name from the prescription text (before the tab character or bracket)
        $drugName = $this->extractDrugName($prescriptionText);
        if (! $drugName) {
            return null;
        }

        // Cache key includes admission to ensure correct match
        $cacheKey = "{$patientId}_{$admissionId}_{$drugName}";
        if (isset($this->prescriptionCache[$cacheKey])) {
            return $this->prescriptionCache[$cacheKey];
        }

        // Get the admission date range to narrow prescription search
        $admission = DB::table('patient_admissions')
            ->where('id', $admissionId)
            ->first(['admitted_at', 'discharged_at']);

        if (! $admission) {
            return null;
        }

        $admittedAt = Carbon::parse($admission->admitted_at)->subDay()->format('Y-m-d');
        $dischargedAt = $admission->discharged_at
            ? Carbon::parse($admission->discharged_at)->addDays(2)->format('Y-m-d')
            : now()->format('Y-m-d');

        // Find prescription for this patient with matching drug name
        // that was created during the same admission period
        $prescription = DB::table('prescriptions')
            ->where('migrated_from_mittag', true)
            ->where('medication_name', 'LIKE', '%' . $drugName . '%')
            ->whereIn('consultation_id', function ($query) use ($patientId, $admittedAt, $dischargedAt) {
                $query->select('id')
                    ->from('consultations')
                    ->whereIn('patient_checkin_id', function ($q) use ($patientId) {
                        $q->select('id')
                            ->from('patient_checkins')
                            ->where('patient_id', $patientId);
                    })
                    ->whereBetween('started_at', [$admittedAt, $dischargedAt]);
            })
            ->value('id');

        $this->prescriptionCache[$cacheKey] = $prescription;

        return $prescription;
    }

    private function extractDrugName(string $text): ?string
    {
        // The format is: "Drug Name, Strength Form\t[dosage instructions]"
        // or: "Drug Name, Strength Form [dosage instructions]"
        $text = trim($text);

        // Split on tab or opening bracket
        $parts = preg_split('/[\t\[]/', $text, 2);
        $name = trim($parts[0] ?? '');

        if (! $name) {
            return null;
        }

        // Take the first meaningful part (drug name + strength)
        // Remove trailing commas and whitespace
        $name = rtrim($name, ', ');

        // Use first 30 chars for matching to avoid minor differences
        return Str::limit($name, 40, '');
    }

    private function parseDateTime(string $date, ?string $time): string
    {
        try {
            if ($time && trim($time) !== '') {
                // Try to parse time like "7:00 AM", "11:30 PM", "3:00 PM"
                $dateTime = Carbon::parse("{$date} {$time}");
            } else {
                $dateTime = Carbon::parse($date);
            }

            return $dateTime->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return Carbon::parse($date)->format('Y-m-d H:i:s');
        }
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

    private function cleanText(?string $text): ?string
    {
        if (! $text || trim($text) === '') {
            return null;
        }

        return trim($text);
    }
}
