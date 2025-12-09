<?php

namespace App\Console\Commands\Migration;

use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigratePatientInsuranceFromMittag extends Command
{
    protected $signature = 'migrate:patient-insurance-from-mittag 
                            {--limit= : Limit number of records to migrate}
                            {--dry-run : Run without actually inserting data}
                            {--skip-existing : Skip patients that already have insurance}';

    protected $description = 'Migrate patient insurance data from Mittag old system to HMS';

    private int $migrated = 0;

    private int $skipped = 0;

    private int $failed = 0;

    private array $patientMap = [];

    private int $nhisInsurancePlanId = 1; // NHIS plan ID

    public function handle(): int
    {
        $this->info('Starting patient insurance migration from Mittag...');

        try {
            DB::connection('mittag_old')->getPdo();
            $this->info('✓ Connected to mittag_old database');
        } catch (\Exception $e) {
            $this->error('Cannot connect to mittag_old database: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Build patient mapping
        $this->buildPatientMap();

        // Get patients with NHIA sponsor
        $query = DB::connection('mittag_old')
            ->table('patients')
            ->where('sponsor', 'nhia')
            ->whereNotNull('memid')
            ->where('memid', '!=', '');

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $total = DB::connection('mittag_old')
            ->table('patients')
            ->where('sponsor', 'nhia')
            ->whereNotNull('memid')
            ->where('memid', '!=', '')
            ->count();

        $this->info("Found {$total} patients with NHIA insurance in Mittag");
        $this->info('Patient map size: '.count($this->patientMap));

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No data will be inserted');
        }

        $bar = $this->output->createProgressBar($this->option('limit') ? (int) $this->option('limit') : $total);
        $bar->start();

        $query->orderBy('id')->chunk(100, function ($patients) use ($bar) {
            foreach ($patients as $oldPatient) {
                $this->migratePatientInsurance($oldPatient);
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

    private function migratePatientInsurance(object $old): void
    {
        try {
            // Find patient by folder_id
            $folderId = trim($old->folder_id);
            $patientId = $this->patientMap[$folderId] ?? null;

            if (! $patientId) {
                $this->failed++;

                return;
            }

            // Check if patient already has insurance
            if ($this->option('skip-existing')) {
                $existing = DB::table('patient_insurance')
                    ->where('patient_id', $patientId)
                    ->where('insurance_plan_id', $this->nhisInsurancePlanId)
                    ->first();

                if ($existing) {
                    $this->skipped++;

                    return;
                }
            }

            if ($this->option('dry-run')) {
                $this->migrated++;

                return;
            }

            // Parse expiration date from old system
            $expirationDate = null;
            if ($old->expiration && $old->expiration !== '0000-00-00') {
                $expirationDate = $old->expiration;
            }

            // Use date_created as start date, or 1 year before expiration
            $startDate = $old->date_created && $old->date_created !== '0000-00-00'
                ? $old->date_created
                : ($expirationDate ? Carbon::parse($expirationDate)->subYear()->format('Y-m-d') : Carbon::now()->subYear()->format('Y-m-d'));

            // Determine status based on expiration
            $status = 'active';
            if ($expirationDate && $expirationDate < now()->format('Y-m-d')) {
                $status = 'expired';
            }

            // Create patient insurance record
            DB::table('patient_insurance')->insert([
                'patient_id' => $patientId,
                'insurance_plan_id' => $this->nhisInsurancePlanId,
                'membership_id' => trim($old->memid),
                'policy_number' => null,
                'folder_id_prefix' => null,
                'is_dependent' => false,
                'principal_member_name' => null,
                'relationship_to_principal' => 'self',
                'coverage_start_date' => $startDate,
                'coverage_end_date' => $expirationDate,
                'status' => $status,
                'card_number' => trim($old->memid),
                'notes' => 'Migrated from Mittag',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->migrated++;

        } catch (\Exception $e) {
            $this->failed++;
            Log::error('Patient insurance migration failed', [
                'folder_id' => $old->folder_id,
                'memid' => $old->memid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
