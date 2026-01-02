<?php

namespace App\Console\Commands;

use App\Models\Charge;
use App\Models\PatientAdmission;
use App\Models\PatientInsurance;
use App\Models\WardBillingTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateDailyWardCharges extends Command
{
    protected $signature = 'admissions:generate-daily-charges 
                            {--date= : The date to generate charges for (defaults to today)}
                            {--dry-run : Show what would be charged without creating charges}';

    protected $description = 'Generate daily recurring charges for all active admissions';

    public function handle(): int
    {
        $date = $this->option('date') ? now()->parse($this->option('date')) : now();
        $isDryRun = $this->option('dry-run');

        $this->info("Generating daily ward charges for {$date->toDateString()}...");

        // Get active billing templates
        $templates = WardBillingTemplate::active()
            ->effective($date)
            ->where('billing_type', 'daily')
            ->get();

        if ($templates->isEmpty()) {
            $this->warn('No active daily billing templates found.');

            return self::SUCCESS;
        }

        $this->info("Found {$templates->count()} active daily billing template(s).");

        // Get all active admissions with patient insurance info
        $admissions = PatientAdmission::where('status', 'admitted')
            ->with(['patient.insurancePlans.plan.provider', 'ward', 'consultation.patientCheckin'])
            ->get();

        if ($admissions->isEmpty()) {
            $this->info('No active admissions found.');

            return self::SUCCESS;
        }

        $this->info("Found {$admissions->count()} active admission(s).");

        $chargesCreated = 0;
        $chargesSkipped = 0;
        $errors = 0;

        foreach ($admissions as $admission) {
            // Check if patient has active NHIS insurance
            $isNhis = $this->patientHasActiveNhis($admission->patient);

            foreach ($templates as $template) {
                try {
                    // Check if charge already exists for this admission, template, and date
                    $existingCharge = Charge::where('patient_checkin_id', $admission->consultation?->patient_checkin_id)
                        ->where('service_code', $template->service_code)
                        ->whereDate('charged_at', $date)
                        ->exists();

                    if ($existingCharge) {
                        $chargesSkipped++;

                        continue;
                    }

                    // Check calculation rules
                    $rules = $template->calculation_rules ?? [];

                    // Skip if admission day and charge_on_admission_day is false
                    if ($admission->admitted_at->isSameDay($date) && ! ($rules['charge_on_admission_day'] ?? true)) {
                        $chargesSkipped++;

                        continue;
                    }

                    // Calculate amount based on NHIS status
                    $amount = $template->calculateAmount(['is_nhis' => $isNhis]);

                    // Skip creating charge if amount is 0 (NHIS patients with free ward fees)
                    if ($amount <= 0) {
                        $chargesSkipped++;
                        if ($isDryRun) {
                            $this->line("  [DRY RUN] Skipping {$admission->patient->full_name} (NHIS): {$template->service_name} - GHS 0.00");
                        }

                        continue;
                    }

                    if ($isDryRun) {
                        $nhisLabel = $isNhis ? ' (NHIS)' : '';
                        $this->line("  [DRY RUN] Would charge {$admission->patient->full_name}{$nhisLabel}: {$template->service_name} - GHS {$amount}");
                        $chargesCreated++;

                        continue;
                    }

                    // Create the charge
                    DB::transaction(function () use ($admission, $template, $date, $amount, $isNhis) {
                        Charge::create([
                            'patient_checkin_id' => $admission->consultation?->patient_checkin_id,
                            'service_type' => 'ward',
                            'service_code' => $template->service_code,
                            'description' => "{$template->service_name} - {$date->toDateString()}",
                            'amount' => $amount,
                            'charge_type' => 'service',
                            'status' => 'pending',
                            'charged_at' => $date,
                            'metadata' => [
                                'admission_id' => $admission->id,
                                'ward_id' => $admission->ward_id,
                                'ward_name' => $admission->ward?->name,
                                'template_id' => $template->id,
                                'billing_type' => $template->billing_type,
                                'generated_by' => 'daily_scheduler',
                                'is_nhis' => $isNhis,
                                'base_amount' => $template->base_amount,
                                'nhis_amount' => $template->nhis_amount,
                            ],
                        ]);
                    });

                    $chargesCreated++;
                    $nhisLabel = $isNhis ? ' (NHIS)' : '';
                    $this->line("  Created charge for {$admission->patient->full_name}{$nhisLabel}: {$template->service_name} - GHS {$amount}");
                } catch (\Exception $e) {
                    $errors++;
                    Log::error('Failed to create daily ward charge', [
                        'admission_id' => $admission->id,
                        'template_id' => $template->id,
                        'error' => $e->getMessage(),
                    ]);
                    $this->error("  Error for admission {$admission->admission_number}: {$e->getMessage()}");
                }
            }
        }

        $this->newLine();
        $this->info('Summary:');
        $this->line("  Charges created: {$chargesCreated}");
        $this->line("  Charges skipped (already exist or NHIS free): {$chargesSkipped}");

        if ($errors > 0) {
            $this->error("  Errors: {$errors}");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Check if patient has active NHIS insurance.
     */
    private function patientHasActiveNhis($patient): bool
    {
        if (! $patient || ! $patient->insurancePlans) {
            return false;
        }

        return $patient->insurancePlans
            ->filter(fn (PatientInsurance $insurance) => $insurance->isActive() && $insurance->isNhis())
            ->isNotEmpty();
    }
}
