<?php

namespace App\Console\Commands\Migration;

use App\Models\ImagingAttachment;
use App\Models\LabOrder;
use App\Models\LabService;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MigrateImagingFromMittag extends Command
{
    protected $signature = 'migrate:imaging-from-mittag 
                            {--limit= : Limit number of records to migrate}
                            {--dry-run : Run without actually inserting data}
                            {--skip-existing : Skip orders that already exist}
                            {--old-uploads-path= : Path to old Mittag uploads directory}
                            {--create-services : Create missing imaging services from gdrg_img}';

    protected $description = 'Migrate imaging/radiology orders from Mittag old system to HMS';

    private int $migrated = 0;

    private int $skipped = 0;

    private int $failed = 0;

    private int $attachmentsMigrated = 0;

    private int $commentsMigrated = 0;

    private int $servicesCreated = 0;

    private array $patientMap = [];

    private array $checkinMap = [];

    private array $labServiceMap = [];

    private ?string $oldUploadsPath = null;

    public function handle(): int
    {
        $this->info('Starting imaging/radiology migration from Mittag...');

        try {
            DB::connection('mittag_old')->getPdo();
            $this->info('✓ Connected to mittag_old database');
        } catch (\Exception $e) {
            $this->error('Cannot connect to mittag_old database: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Set old uploads path
        $this->oldUploadsPath = $this->option('old-uploads-path');
        if (! $this->oldUploadsPath) {
            // Default to a common location - user can override
            $this->oldUploadsPath = base_path('../mittag_old_uploads');
        }

        // Create missing imaging services if requested
        if ($this->option('create-services')) {
            $this->createMissingImagingServices();
        }

        // Build mappings
        $this->buildPatientMap();
        $this->buildCheckinMap();
        $this->buildLabServiceMap();

        $query = DB::connection('mittag_old')->table('img_daily_register');

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $total = DB::connection('mittag_old')->table('img_daily_register')->count();
        $this->info("Found {$total} imaging orders in Mittag");
        $this->info('Patient map size: '.count($this->patientMap));
        $this->info('Imaging service map size: '.count($this->labServiceMap));

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No data will be inserted');
        }

        $bar = $this->output->createProgressBar($this->option('limit') ? (int) $this->option('limit') : $total);
        $bar->start();

        $query->orderBy('id')->chunk(100, function ($orders) use ($bar) {
            foreach ($orders as $oldOrder) {
                $this->migrateImagingOrder($oldOrder);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info('Migration completed:');
        $this->line("  ✓ Migrated:    {$this->migrated}");
        $this->line("  ⊘ Skipped:     {$this->skipped}");
        $this->line("  ✗ Failed:      {$this->failed}");
        $this->line("  📎 Attachments: {$this->attachmentsMigrated}");
        $this->line("  📝 Comments:    {$this->commentsMigrated}");
        if ($this->servicesCreated > 0) {
            $this->line("  🏥 Services:    {$this->servicesCreated}");
        }

        return Command::SUCCESS;
    }

    private function createMissingImagingServices(): void
    {
        $this->info('Creating missing imaging services from gdrg_img...');

        // Get all imaging codes used in orders
        $usedCodes = DB::connection('mittag_old')
            ->table('img_daily_register')
            ->distinct()
            ->pluck('code')
            ->toArray();

        // Get existing service codes
        $existingCodes = LabService::pluck('code')->toArray();

        // Find missing codes
        $missingCodes = array_diff($usedCodes, $existingCodes);

        if (empty($missingCodes)) {
            $this->info('  All imaging services already exist.');

            return;
        }

        // Get service details from gdrg_img
        $gdrgServices = DB::connection('mittag_old')
            ->table('gdrg_img')
            ->whereIn('code', $missingCodes)
            ->get();

        // Disable observers during migration to avoid notification issues
        LabService::withoutEvents(function () use ($gdrgServices) {
            foreach ($gdrgServices as $gdrg) {
                if ($this->option('dry-run')) {
                    $this->line("  Would create: {$gdrg->code} - {$gdrg->name}");
                    $this->servicesCreated++;

                    continue;
                }

                // Determine modality from name
                $modality = $this->determineModality($gdrg->name);

                LabService::create([
                    'code' => $gdrg->code,
                    'name' => $gdrg->name,
                    'category' => 'Imaging',
                    'description' => null,
                    'preparation_instructions' => null,
                    'price' => null, // Prices managed via Pricing Dashboard
                    'sample_type' => null,
                    'turnaround_time' => '24-48 hours', // Default for imaging
                    'normal_range' => null,
                    'clinical_significance' => null,
                    'test_parameters' => null,
                    'is_active' => true,
                    'is_imaging' => true,
                    'modality' => $modality,
                ]);

                $this->line("  ✓ Created: {$gdrg->code} - {$gdrg->name} ({$modality})");
                $this->servicesCreated++;
            }
        });

        // Handle codes not found in gdrg_img
        $notFoundCodes = array_diff($missingCodes, $gdrgServices->pluck('code')->toArray());
        foreach ($notFoundCodes as $code) {
            $this->warn("  ⚠ Service code not found in gdrg_img: {$code}");
        }
    }

    private function determineModality(string $name): string
    {
        $nameLower = strtolower($name);

        if (str_contains($nameLower, 'ct scan') || str_contains($nameLower, 'ct ')) {
            return 'CT';
        }
        if (str_contains($nameLower, 'mri') || str_contains($nameLower, 'magnetic')) {
            return 'MRI';
        }
        if (str_contains($nameLower, 'x-ray') || str_contains($nameLower, 'xray') || str_contains($nameLower, 'radiograph')) {
            return 'X-Ray';
        }
        if (str_contains($nameLower, 'ultrasound') || str_contains($nameLower, 'scan') || str_contains($nameLower, 'obstetric')) {
            return 'Ultrasound';
        }
        if (str_contains($nameLower, 'mammogra')) {
            return 'Mammography';
        }
        if (str_contains($nameLower, 'fluoro')) {
            return 'Fluoroscopy';
        }

        return 'Other';
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
        // Only map imaging services
        $this->labServiceMap = LabService::where('is_imaging', true)
            ->pluck('id', 'code')
            ->toArray();
    }

    private function migrateImagingOrder(object $old): void
    {
        try {
            // Check if already migrated
            if ($this->option('skip-existing')) {
                $existingLog = DB::table('mittag_migration_logs')
                    ->where('entity_type', 'imaging_order')
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

            // Find imaging service
            $labServiceId = $this->labServiceMap[$old->code] ?? null;

            if (! $labServiceId) {
                // Try to find in all lab services (might not be marked as imaging yet)
                $labServiceId = LabService::where('code', $old->code)->value('id');

                if (! $labServiceId) {
                    $this->logMigration($old, null, 'failed', "Imaging service not found: {$old->code}");
                    $this->failed++;

                    return;
                }
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

            // Get comments/report for this order
            $report = $this->getReportForOrder($old);

            $labOrderData = [
                'consultation_id' => $consultationId,
                'lab_service_id' => $labServiceId,
                'ordered_by' => 1, // System user
                'ordered_at' => $orderedAt,
                'status' => 'completed',
                'priority' => 'routine',
                'special_instructions' => null,
                'sample_collected_at' => null, // Not applicable for imaging
                'result_entered_at' => $report ? $orderedAt : null,
                'result_values' => null, // Imaging doesn't have structured results
                'result_notes' => $report,
                'created_at' => $orderedAt,
                'updated_at' => $orderedAt,
                'migrated_from_mittag' => true,
            ];

            $labOrder = LabOrder::create($labOrderData);

            // Migrate attachments for this order
            $this->migrateAttachmentsForOrder($old, $labOrder, $patientId);

            $this->logMigration($old, $labOrder->id, 'success');
            $this->migrated++;

        } catch (\Exception $e) {
            $this->logMigration($old, null, 'failed', $e->getMessage());
            $this->failed++;
            Log::error('Imaging order migration failed', [
                'old_id' => $old->id,
                'folder_id' => $old->folder_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getReportForOrder(object $order): ?string
    {
        // Get comments from img_comments table
        $comments = DB::connection('mittag_old')
            ->table('img_comments')
            ->where('folder_id', $order->folder_id)
            ->where('date', $order->date)
            ->get();

        if ($comments->isEmpty()) {
            return null;
        }

        $reportParts = [];
        foreach ($comments as $comment) {
            // Strip HTML tags and clean up the content
            $cleanComment = $this->stripHtmlTags($comment->comment);
            if (! empty($cleanComment)) {
                $reportParts[] = $cleanComment;
                $this->commentsMigrated++;
            }
        }

        if (empty($reportParts)) {
            return null;
        }

        return implode("\n\n", $reportParts);
    }

    private function stripHtmlTags(string $html): string
    {
        // Replace <br> and <div> with newlines
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $text = preg_replace('/<\/div>/i', "\n", $text);
        $text = preg_replace('/<div[^>]*>/i', '', $text);

        // Strip remaining HTML tags
        $text = strip_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Clean up multiple newlines and whitespace
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        return $text;
    }

    private function migrateAttachmentsForOrder(object $oldOrder, LabOrder $labOrder, int $patientId): void
    {
        // Get results (file references) from img_results table
        $results = DB::connection('mittag_old')
            ->table('img_results')
            ->where('folder_id', $oldOrder->folder_id)
            ->where('date', $oldOrder->date)
            ->where('code', $oldOrder->code)
            ->get();

        if ($results->isEmpty()) {
            return;
        }

        foreach ($results as $result) {
            try {
                $this->migrateAttachment($result, $labOrder, $patientId);
            } catch (\Exception $e) {
                Log::warning('Failed to migrate imaging attachment', [
                    'result_id' => $result->id,
                    'lab_order_id' => $labOrder->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function migrateAttachment(object $result, LabOrder $labOrder, int $patientId): void
    {
        $oldFilePath = $result->upload_dir;

        if (empty($oldFilePath)) {
            return;
        }

        // Determine file info from old path
        $fileName = basename($oldFilePath);
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Determine MIME type
        $mimeType = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };

        // Generate new storage path
        $year = Carbon::parse($result->date)->format('Y');
        $month = Carbon::parse($result->date)->format('m');
        $newPath = sprintf(
            'imaging/%s/%s/%d/%d/original/%s',
            $year,
            $month,
            $patientId,
            $labOrder->id,
            $fileName
        );

        $fileSize = 0;

        // Try to copy the file if old uploads path is available
        if ($this->oldUploadsPath && ! $this->option('dry-run')) {
            $fullOldPath = rtrim($this->oldUploadsPath, '/').'/'.$oldFilePath;

            if (file_exists($fullOldPath)) {
                // Ensure directory exists
                $newDir = dirname(Storage::disk('local')->path($newPath));
                if (! is_dir($newDir)) {
                    mkdir($newDir, 0755, true);
                }

                // Copy file
                $newFullPath = Storage::disk('local')->path($newPath);
                if (copy($fullOldPath, $newFullPath)) {
                    $fileSize = filesize($newFullPath);
                }
            } else {
                Log::info('Old imaging file not found, creating reference only', [
                    'old_path' => $fullOldPath,
                    'new_path' => $newPath,
                ]);
            }
        }

        // Create attachment record
        ImagingAttachment::create([
            'lab_order_id' => $labOrder->id,
            'file_path' => $newPath,
            'file_name' => $fileName,
            'file_type' => $mimeType,
            'file_size' => $fileSize,
            'description' => 'Migrated from Mittag',
            'is_external' => false,
            'external_facility_name' => null,
            'external_study_date' => null,
            'uploaded_by' => 1, // System user
            'uploaded_at' => Carbon::parse($result->date),
        ]);

        $this->attachmentsMigrated++;
    }

    private function logMigration(object $old, ?int $newId, string $status, ?string $notes = null): void
    {
        if ($this->option('dry-run')) {
            return;
        }

        DB::table('mittag_migration_logs')->updateOrInsert(
            [
                'entity_type' => 'imaging_order',
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
