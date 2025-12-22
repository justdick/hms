<?php

namespace App\Console\Commands\Migration;

use App\Models\Department;
use App\Models\DepartmentBilling;
use App\Models\Drug;
use App\Models\InsuranceCoverageRule;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\LabService;
use App\Models\MinorProcedureType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportMittagPricing extends Command
{
    protected $signature = 'migrate:import-mittag-pricing 
                            {--dry-run : Show what would be imported without making changes}
                            {--type= : Import specific type only (drugs, lab, imaging, procedures, consultation)}';

    protected $description = 'Import pricing data (cash_price and top_up) from mittag_old database';

    protected int $nhisplanId;

    protected array $stats = [
        'drugs' => ['updated' => 0, 'skipped' => 0, 'copay_created' => 0],
        'lab' => ['updated' => 0, 'skipped' => 0, 'copay_created' => 0],
        'imaging' => ['updated' => 0, 'skipped' => 0, 'copay_created' => 0],
        'procedures' => ['updated' => 0, 'skipped' => 0, 'copay_created' => 0],
        'consultation' => ['updated' => 0, 'skipped' => 0, 'copay_created' => 0],
    ];

    public function handle(): int
    {
        $this->info('Starting Mittag pricing import...');

        // Find NHIS plan
        $nhisProvider = InsuranceProvider::where('is_nhis', true)->first();
        if (! $nhisProvider) {
            $this->error('NHIS provider not found. Please create it first.');

            return self::FAILURE;
        }

        $nhisPlan = InsurancePlan::where('insurance_provider_id', $nhisProvider->id)->first();
        if (! $nhisPlan) {
            $this->error('NHIS plan not found. Please create it first.');

            return self::FAILURE;
        }

        $this->nhisplanId = $nhisPlan->id;
        $this->info("Using NHIS Plan: {$nhisPlan->plan_name} (ID: {$this->nhisplanId})");

        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $type = $this->option('type');

        if (! $type || $type === 'drugs') {
            $this->importDrugs($isDryRun);
        }

        if (! $type || $type === 'lab') {
            $this->importLabServices($isDryRun);
        }

        if (! $type || $type === 'imaging') {
            $this->importImaging($isDryRun);
        }

        if (! $type || $type === 'procedures') {
            $this->importProcedures($isDryRun);
        }

        if (! $type || $type === 'consultation') {
            $this->importConsultationFees($isDryRun);
        }

        $this->displaySummary();

        return self::SUCCESS;
    }

    protected function importDrugs(bool $isDryRun): void
    {
        $this->info("\n📦 Importing Drug Prices...");

        // Get drugs from mittag_old that have pricing
        $mittagDrugs = DB::connection('mittag_old')
            ->table('drugs')
            ->where(function ($query) {
                $query->where('cash_price', '>', 0)
                    ->orWhere('top_up', '>', 0);
            })
            ->get();

        $this->info("Found {$mittagDrugs->count()} drugs with pricing in mittag_old");

        $progressBar = $this->output->createProgressBar($mittagDrugs->count());

        foreach ($mittagDrugs as $mittagDrug) {
            $hmsDrug = Drug::where('drug_code', $mittagDrug->code)->first();

            if (! $hmsDrug) {
                $this->stats['drugs']['skipped']++;
                $progressBar->advance();

                continue;
            }

            if (! $isDryRun) {
                // Update cash price
                if ($mittagDrug->cash_price > 0) {
                    $hmsDrug->update(['unit_price' => $mittagDrug->cash_price]);
                    $this->stats['drugs']['updated']++;
                }

                // Create NHIS copay rule if top_up > 0
                if ($mittagDrug->top_up > 0) {
                    $this->createOrUpdateCopayRule(
                        'drug',
                        $mittagDrug->code,
                        $hmsDrug->name,
                        $mittagDrug->top_up
                    );
                    $this->stats['drugs']['copay_created']++;
                }
            } else {
                if ($mittagDrug->cash_price > 0) {
                    $this->stats['drugs']['updated']++;
                }
                if ($mittagDrug->top_up > 0) {
                    $this->stats['drugs']['copay_created']++;
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
    }

    protected function importLabServices(bool $isDryRun): void
    {
        $this->info("\n🔬 Importing Lab Service Prices...");

        $mittagLabs = DB::connection('mittag_old')
            ->table('gdrg_lab')
            ->where(function ($query) {
                $query->where('cash_price', '>', 0)
                    ->orWhere('top_up', '>', 0);
            })
            ->get();

        $this->info("Found {$mittagLabs->count()} lab services with pricing in mittag_old");

        $progressBar = $this->output->createProgressBar($mittagLabs->count());

        foreach ($mittagLabs as $mittagLab) {
            $hmsLab = LabService::where('code', $mittagLab->code)
                ->where('is_imaging', false)
                ->first();

            if (! $hmsLab) {
                $this->stats['lab']['skipped']++;
                $progressBar->advance();

                continue;
            }

            if (! $isDryRun) {
                if ($mittagLab->cash_price > 0) {
                    $hmsLab->update(['price' => $mittagLab->cash_price]);
                    $this->stats['lab']['updated']++;
                }

                if ($mittagLab->top_up > 0) {
                    $this->createOrUpdateCopayRule(
                        'lab',
                        $mittagLab->code,
                        $mittagLab->name,
                        $mittagLab->top_up
                    );
                    $this->stats['lab']['copay_created']++;
                }
            } else {
                if ($mittagLab->cash_price > 0) {
                    $this->stats['lab']['updated']++;
                }
                if ($mittagLab->top_up > 0) {
                    $this->stats['lab']['copay_created']++;
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
    }

    protected function importImaging(bool $isDryRun): void
    {
        $this->info("\n📷 Importing Imaging Service Prices...");

        $mittagImaging = DB::connection('mittag_old')
            ->table('gdrg_img')
            ->where(function ($query) {
                $query->where('cash_price', '>', 0)
                    ->orWhere('top_up', '>', 0);
            })
            ->get();

        $this->info("Found {$mittagImaging->count()} imaging services with pricing in mittag_old");

        $progressBar = $this->output->createProgressBar($mittagImaging->count());

        foreach ($mittagImaging as $mittagImg) {
            // Look in lab_services by code (imaging may not have is_imaging flag set)
            $hmsImaging = LabService::where('code', $mittagImg->code)->first();

            if (! $hmsImaging) {
                $this->stats['imaging']['skipped']++;
                $progressBar->advance();

                continue;
            }

            if (! $isDryRun) {
                $updateData = ['is_imaging' => true];

                if ($mittagImg->cash_price > 0) {
                    $updateData['price'] = $mittagImg->cash_price;
                    $this->stats['imaging']['updated']++;
                }

                $hmsImaging->update($updateData);

                if ($mittagImg->top_up > 0) {
                    $this->createOrUpdateCopayRule(
                        'lab',
                        $mittagImg->code,
                        $mittagImg->name,
                        $mittagImg->top_up
                    );
                    $this->stats['imaging']['copay_created']++;
                }
            } else {
                if ($mittagImg->cash_price > 0) {
                    $this->stats['imaging']['updated']++;
                }
                if ($mittagImg->top_up > 0) {
                    $this->stats['imaging']['copay_created']++;
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
    }

    protected function importProcedures(bool $isDryRun): void
    {
        $this->info("\n🏥 Importing Procedure Prices...");

        $mittagProcedures = DB::connection('mittag_old')
            ->table('gdrg_main')
            ->where(function ($query) {
                $query->where('cash_price', '>', 0)
                    ->orWhere('top_up', '>', 0);
            })
            ->get();

        $this->info("Found {$mittagProcedures->count()} procedures with pricing in mittag_old");

        $progressBar = $this->output->createProgressBar($mittagProcedures->count());

        foreach ($mittagProcedures as $mittagProc) {
            $hmsProc = MinorProcedureType::where('code', $mittagProc->code)->first();

            if (! $hmsProc) {
                $this->stats['procedures']['skipped']++;
                $progressBar->advance();

                continue;
            }

            if (! $isDryRun) {
                if ($mittagProc->cash_price > 0) {
                    $hmsProc->update(['price' => $mittagProc->cash_price]);
                    $this->stats['procedures']['updated']++;
                }

                if ($mittagProc->top_up > 0) {
                    $this->createOrUpdateCopayRule(
                        'procedure',
                        $mittagProc->code,
                        $mittagProc->name,
                        $mittagProc->top_up
                    );
                    $this->stats['procedures']['copay_created']++;
                }
            } else {
                if ($mittagProc->cash_price > 0) {
                    $this->stats['procedures']['updated']++;
                }
                if ($mittagProc->top_up > 0) {
                    $this->stats['procedures']['copay_created']++;
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
    }

    protected function importConsultationFees(bool $isDryRun): void
    {
        $this->info("\n🩺 Importing Consultation Fees...");

        // Default consultation fees from mittag_old billing records
        $cashPrice = 40.00;  // Cash/Uninsured patients
        $nhisTopUp = 20.00;  // NHIS patient copay

        $this->info("Setting default consultation fees: Cash = GH₵{$cashPrice}, NHIS Copay = GH₵{$nhisTopUp}");

        // Get all departments
        $departments = Department::where('is_active', true)->get();

        $this->info("Found {$departments->count()} active departments");

        $progressBar = $this->output->createProgressBar($departments->count());

        foreach ($departments as $department) {
            if (! $isDryRun) {
                // Create or update department billing
                DepartmentBilling::updateOrCreate(
                    ['department_id' => $department->id],
                    [
                        'department_code' => $department->code,
                        'department_name' => $department->name,
                        'consultation_fee' => $cashPrice,
                        'is_active' => true,
                    ]
                );
                $this->stats['consultation']['updated']++;

                // Create NHIS copay rule for consultation
                $this->createOrUpdateCopayRule(
                    'consultation',
                    $department->code,
                    $department->name.' Consultation',
                    $nhisTopUp
                );
                $this->stats['consultation']['copay_created']++;
            } else {
                $this->stats['consultation']['updated']++;
                $this->stats['consultation']['copay_created']++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
    }

    protected function createOrUpdateCopayRule(
        string $category,
        string $itemCode,
        string $itemDescription,
        float $copayAmount
    ): void {
        InsuranceCoverageRule::updateOrCreate(
            [
                'insurance_plan_id' => $this->nhisplanId,
                'coverage_category' => $category,
                'item_code' => $itemCode,
            ],
            [
                'item_description' => trim($itemDescription),
                'is_covered' => true,
                'coverage_type' => 'full',
                'coverage_value' => 100,
                'patient_copay_amount' => $copayAmount,
                'is_active' => true,
            ]
        );
    }

    protected function displaySummary(): void
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $this->info('           IMPORT SUMMARY              ');
        $this->info('═══════════════════════════════════════');

        $headers = ['Type', 'Prices Updated', 'Copay Rules Created', 'Skipped (Not Found)'];
        $rows = [];

        foreach ($this->stats as $type => $stat) {
            $rows[] = [
                ucfirst($type),
                $stat['updated'],
                $stat['copay_created'],
                $stat['skipped'],
            ];
        }

        $this->table($headers, $rows);

        $totalUpdated = array_sum(array_column($this->stats, 'updated'));
        $totalCopay = array_sum(array_column($this->stats, 'copay_created'));
        $totalSkipped = array_sum(array_column($this->stats, 'skipped'));

        $this->info("Total: {$totalUpdated} prices updated, {$totalCopay} copay rules created, {$totalSkipped} skipped");
    }
}
