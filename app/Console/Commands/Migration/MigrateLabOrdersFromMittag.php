<?php

namespace App\Console\Commands\Migration;

use App\Models\LabOrder;
use App\Models\LabService;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateLabOrdersFromMittag extends Command
{
    protected $signature = 'migrate:lab-orders-from-mittag 
                            {--limit= : Limit number of records to migrate}
                            {--dry-run : Run without actually inserting data}
                            {--skip-existing : Skip orders that already exist}';

    protected $description = 'Migrate lab orders from Mittag old system to HMS';

    private int $migrated = 0;

    private int $skipped = 0;

    private int $failed = 0;

    private array $patientMap = [];

    private array $checkinMap = [];

    private array $labServiceMap = [];

    public function handle(): int
    {
        $this->info('Starting lab orders migration from Mittag...');

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
        $this->buildLabServiceMap();

        $query = DB::connection('mittag_old')->table('lab_daily_register');

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $total = DB::connection('mittag_old')->table('lab_daily_register')->count();
        $this->info("Found {$total} lab orders in Mittag");
        $this->info('Patient map size: '.count($this->patientMap));
        $this->info('Lab service map size: '.count($this->labServiceMap));

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No data will be inserted');
        }

        $bar = $this->output->createProgressBar($this->option('limit') ? (int) $this->option('limit') : $total);
        $bar->start();

        $query->orderBy('id')->chunk(100, function ($orders) use ($bar) {
            foreach ($orders as $oldOrder) {
                $this->migrateLabOrder($oldOrder);
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

    private function buildLabServiceMap(): void
    {
        $this->labServiceMap = LabService::pluck('id', 'code')->toArray();
    }

    private function migrateLabOrder(object $old): void
    {
        try {
            // Check if already migrated
            if ($this->option('skip-existing')) {
                $existingLog = DB::table('mittag_migration_logs')
                    ->where('entity_type', 'lab_order')
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

            // Find lab service
            $labServiceId = $this->labServiceMap[$old->code] ?? null;

            if (! $labServiceId) {
                $this->logMigration($old, null, 'failed', "Lab service not found: {$old->code}");
                $this->failed++;

                return;
            }

            // Find consultation via checkin
            $key = "{$folderId}_{$old->date}";
            $checkinId = $this->checkinMap[$key] ?? null;
            $consultationId = null;

            if ($checkinId) {
                $consultation = DB::table('consultations')
                    ->where('patient_checkin_id', $checkinId)
                    ->first();
                $consultationId = $consultation?->id;
            }

            if ($this->option('dry-run')) {
                $this->migrated++;

                return;
            }

            $orderedAt = Carbon::parse($old->date);

            // Get results for this order
            $results = $this->getResultsForOrder($old);

            $labOrderData = [
                'consultation_id' => $consultationId,
                'lab_service_id' => $labServiceId,
                'ordered_by' => 1, // System user
                'ordered_at' => $orderedAt,
                'status' => 'completed',
                'priority' => 'routine',
                'special_instructions' => null,
                'sample_collected_at' => $orderedAt,
                'result_entered_at' => $results ? $orderedAt : null,
                'result_values' => $results ? json_encode($results) : null,
                'result_notes' => 'Migrated from Mittag',
                'created_at' => $orderedAt,
                'updated_at' => $orderedAt,
                'migrated_from_mittag' => true,
            ];

            $labOrder = LabOrder::create($labOrderData);

            $this->logMigration($old, $labOrder->id, 'success');
            $this->migrated++;

        } catch (\Exception $e) {
            $this->logMigration($old, null, 'failed', $e->getMessage());
            $this->failed++;
            Log::error('Lab order migration failed', [
                'old_id' => $old->id,
                'folder_id' => $old->folder_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getResultsForOrder(object $order): ?array
    {
        $results = DB::connection('mittag_old')
            ->table('lab_results')
            ->where('folder_id', $order->folder_id)
            ->where('date', $order->date)
            ->where('code', $order->code)
            ->get();

        if ($results->isEmpty()) {
            return null;
        }

        $resultValues = [];
        foreach ($results as $result) {
            $resultValues[$result->test] = [
                'value' => $result->result,
                'normal_range' => $result->standard,
            ];
        }

        return $resultValues;
    }

    private function logMigration(object $old, ?int $newId, string $status, ?string $notes = null): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        DB::table('mittag_migration_logs')->updateOrInsert(
            [
                'entity_type' => 'lab_order',
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
                    'code' => $old->code,
                ]),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
