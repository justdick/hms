<?php

namespace Database\Seeders;

use App\Models\Charge;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimItem;
use App\Models\InsurancePlan;
use App\Models\InsuranceProvider;
use App\Models\Patient;
use App\Models\PatientInsurance;
use App\Models\User;
use Illuminate\Database\Seeder;

class InsuranceReportsSeeder extends Seeder
{
    private array $providers;

    private array $plans;

    private array $vettingOfficers;

    private array $rejectionReasons = [
        'Incomplete documentation',
        'Service not covered under plan',
        'Pre-authorization not obtained',
        'Diagnosis code mismatch',
        'Treatment not medically necessary',
        'Patient coverage expired at time of service',
        'Duplicate claim submission',
        'Incorrect billing codes',
        'Missing required referral',
        'Exceeds annual limit',
    ];

    private array $serviceTypes = [
        ['type' => 'consultation', 'charge_type' => 'consultation_fee', 'descriptions' => ['General Consultation', 'Specialist Consultation', 'Follow-up Visit', 'Emergency Consultation']],
        ['type' => 'drug', 'charge_type' => 'medication', 'descriptions' => ['Antibiotics', 'Pain Medication', 'Blood Pressure Medication', 'Diabetes Medication', 'Vitamins']],
        ['type' => 'lab', 'charge_type' => 'lab_test', 'descriptions' => ['Complete Blood Count', 'Urinalysis', 'Blood Sugar Test', 'Lipid Profile', 'Liver Function Test']],
        ['type' => 'procedure', 'charge_type' => 'procedure', 'descriptions' => ['Minor Surgery', 'Wound Dressing', 'Injection', 'IV Therapy', 'Ultrasound Scan']],
        ['type' => 'ward', 'charge_type' => 'ward_bed', 'descriptions' => ['General Ward Admission', 'ICU Admission', 'Maternity Ward', 'Pediatric Ward']],
        ['type' => 'nursing', 'charge_type' => 'nursing_care', 'descriptions' => ['Nursing Care', 'Post-op Monitoring', 'Vital Signs Monitoring', 'Medication Administration']],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting Insurance Reports Seeder...');

        // Ensure we have providers and plans
        $this->setupProviders();
        $this->setupVettingOfficers();

        // Create patients with insurance coverage
        $this->command->info('Creating patients with insurance coverage...');
        $patients = $this->createPatientsWithInsurance(50);

        // Generate claims for the last 6 months
        $this->command->info('Generating insurance claims for the last 6 months...');
        $this->generateClaimsForLastSixMonths($patients);

        // Generate charges without insurance (for revenue analysis comparison)
        $this->command->info('Generating cash charges for revenue comparison...');
        $this->generateCashCharges();

        $this->command->info('Insurance Reports Seeder completed successfully!');
    }

    private function setupProviders(): void
    {
        $this->providers = InsuranceProvider::active()->get()->toArray();

        if (empty($this->providers)) {
            $this->command->warn('No insurance providers found. Creating sample providers...');
            (new InsuranceSeeder)->run();
            $this->providers = InsuranceProvider::active()->get()->toArray();
        }

        $this->plans = InsurancePlan::active()->get()->toArray();
    }

    private function setupVettingOfficers(): void
    {
        // Get existing users or create vetting officers
        $this->vettingOfficers = User::limit(3)->get()->toArray();

        if (empty($this->vettingOfficers)) {
            $this->command->warn('No users found for vetting officers. Using admin user if available.');
            $this->vettingOfficers = User::where('email', 'admin@example.com')->limit(1)->get()->toArray();
        }
    }

    private function createPatientsWithInsurance(int $count): array
    {
        $patients = [];

        for ($i = 0; $i < $count; $i++) {
            $patient = Patient::factory()->create();

            // Assign random insurance plan
            $plan = $this->plans[array_rand($this->plans)];

            PatientInsurance::create([
                'patient_id' => $patient->id,
                'insurance_plan_id' => $plan['id'],
                'membership_id' => str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT),
                'policy_number' => 'POL'.str_pad($i + rand(1000, 9999), 7, '0', STR_PAD_LEFT),
                'folder_id_prefix' => substr($patient->patient_number, 0, 2),
                'is_dependent' => rand(0, 100) < 30,
                'principal_member_name' => rand(0, 100) < 30 ? fake()->name() : null,
                'relationship_to_principal' => rand(0, 100) < 30 ? fake()->randomElement(['spouse', 'child', 'parent']) : 'self',
                'coverage_start_date' => now()->subYear(),
                'coverage_end_date' => now()->addYear(),
                'status' => 'active',
                'card_number' => 'CARD'.str_pad($i + rand(1000, 9999), 10, '0', STR_PAD_LEFT),
                'notes' => null,
            ]);

            $patients[] = $patient;
        }

        return $patients;
    }

    private function generateClaimsForLastSixMonths(array $patients): void
    {
        $totalClaims = 0;

        // Generate claims distributed over 6 months
        for ($monthOffset = 5; $monthOffset >= 0; $monthOffset--) {
            $monthStart = now()->subMonths($monthOffset)->startOfMonth();
            $monthEnd = now()->subMonths($monthOffset)->endOfMonth();

            // Generate 30-80 claims per month
            $claimsThisMonth = rand(30, 80);

            for ($i = 0; $i < $claimsThisMonth; $i++) {
                $this->createClaim($patients, $monthStart, $monthEnd);
                $totalClaims++;
            }

            $this->command->info("Generated {$claimsThisMonth} claims for {$monthStart->format('M Y')}");
        }

        $this->command->info("Total claims generated: {$totalClaims}");
    }

    private function createClaim(array $patients, $startDate, $endDate): void
    {
        $patient = $patients[array_rand($patients)];
        $patientInsurance = PatientInsurance::where('patient_id', $patient->id)->first();

        if (! $patientInsurance) {
            return;
        }

        $attendanceDate = fake()->dateTimeBetween($startDate, $endDate);
        $createdAt = $attendanceDate;

        // Create a patient checkin for this claim
        $checkin = \App\Models\PatientCheckin::create([
            'patient_id' => $patient->id,
            'department_id' => rand(1, 5), // Random department from seeded departments
            'checked_in_by' => 1, // Default user
            'checked_in_at' => $attendanceDate,
            'status' => 'completed',
            'claim_check_code' => 'CHK'.str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT),
            'consultation_completed_at' => (clone $attendanceDate)->modify('+'.rand(1, 4).' hours'),
        ]);

        // Determine claim status and dates based on realistic workflow
        $statusDistribution = [
            'draft' => 5,
            'vetted' => 10,
            'submitted' => 15,
            'approved' => 25,
            'paid' => 30,
            'partial' => 5,
            'rejected' => 10,
        ];

        $status = $this->weightedRandom($statusDistribution);

        // Calculate timestamps based on status
        $vettedBy = null;
        $vettedAt = null;
        $submittedBy = null;
        $submittedAt = null;
        $submissionDate = null;
        $approvalDate = null;
        $paymentDate = null;
        $rejectionReason = null;
        $rejectedBy = null;
        $rejectedAt = null;

        if (in_array($status, ['vetted', 'submitted', 'approved', 'paid', 'partial', 'rejected'])) {
            // Claim was vetted (1-3 days after creation)
            $vettedAt = (clone $createdAt)->modify('+'.rand(1, 3).' days');
            $vettedBy = ! empty($this->vettingOfficers) ? $this->vettingOfficers[array_rand($this->vettingOfficers)]['id'] : null;
        }

        if (in_array($status, ['submitted', 'approved', 'paid', 'partial'])) {
            // Claim was submitted (1-2 days after vetting)
            $submittedAt = (clone $vettedAt)->modify('+'.rand(1, 2).' days');
            $submissionDate = $submittedAt->format('Y-m-d');
            $submittedBy = $vettedBy;
        }

        if (in_array($status, ['approved', 'paid', 'partial'])) {
            // Claim was approved (7-30 days after submission)
            $approvalDate = (clone $submittedAt)->modify('+'.rand(7, 30).' days')->format('Y-m-d');
        }

        if (in_array($status, ['paid', 'partial'])) {
            // Payment received (15-45 days after approval)
            $paymentDate = (clone $submittedAt)->modify('+'.rand(15, 45).' days')->format('Y-m-d');
        }

        if ($status === 'rejected') {
            $rejectionReason = $this->rejectionReasons[array_rand($this->rejectionReasons)];
            $rejectedAt = (clone $createdAt)->modify('+'.rand(5, 15).' days');
            $rejectedBy = ! empty($this->vettingOfficers) ? $this->vettingOfficers[array_rand($this->vettingOfficers)]['id'] : null;
        }

        // Generate claim items
        $itemsCount = rand(1, 8);
        $totalClaimAmount = 0;
        $claimItems = [];

        for ($j = 0; $j < $itemsCount; $j++) {
            $serviceType = $this->serviceTypes[array_rand($this->serviceTypes)];
            $description = $serviceType['descriptions'][array_rand($serviceType['descriptions'])];

            $quantity = rand(1, 5);
            $unitTariff = fake()->randomFloat(2, 20, 800);
            $subtotal = $quantity * $unitTariff;

            // Get coverage percentage from plan
            $plan = InsurancePlan::find($patientInsurance->insurance_plan_id);
            $coveragePercentage = $plan ? (100 - $plan->default_copay_percentage) : 80;

            $insurancePays = $subtotal * ($coveragePercentage / 100);
            $patientPays = $subtotal - $insurancePays;

            $claimItems[] = [
                'item_date' => $attendanceDate->format('Y-m-d'),
                'item_type' => $serviceType['type'],
                'charge_type' => $serviceType['charge_type'],
                'code' => strtoupper(fake()->bothify('??###??')),
                'description' => $description,
                'quantity' => $quantity,
                'unit_tariff' => $unitTariff,
                'subtotal' => $subtotal,
                'is_covered' => true,
                'coverage_percentage' => $coveragePercentage,
                'insurance_pays' => $insurancePays,
                'patient_pays' => $patientPays,
                'is_approved' => in_array($status, ['approved', 'paid', 'partial']),
                'rejection_reason' => null,
                'notes' => null,
            ];

            $totalClaimAmount += $subtotal;
        }

        // Calculate approved and payment amounts
        $approvedAmount = 0;
        $insuranceCoveredAmount = 0;
        $patientCopayAmount = 0;
        $paymentAmount = 0;

        if (in_array($status, ['approved', 'paid', 'partial'])) {
            $approvedAmount = $totalClaimAmount * 0.95;
            $insuranceCoveredAmount = array_sum(array_column($claimItems, 'insurance_pays'));
            $patientCopayAmount = array_sum(array_column($claimItems, 'patient_pays'));
        }

        if ($status === 'paid') {
            $paymentAmount = $approvedAmount;
        } elseif ($status === 'partial') {
            $paymentAmount = $approvedAmount * 0.7;
        }

        // Create the claim
        $claim = InsuranceClaim::create([
            'claim_check_code' => 'CLM'.str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT),
            'folder_id' => substr($patient->patient_number, 0, 2).str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
            'patient_id' => $patient->id,
            'patient_insurance_id' => $patientInsurance->id,
            'patient_checkin_id' => $checkin->id,
            'consultation_id' => null,
            'patient_admission_id' => null,
            'patient_surname' => $patient->last_name,
            'patient_other_names' => $patient->first_name,
            'patient_dob' => $patient->date_of_birth,
            'patient_gender' => $patient->gender,
            'membership_id' => $patientInsurance->membership_id,
            'date_of_attendance' => $attendanceDate->format('Y-m-d'),
            'date_of_discharge' => rand(0, 100) < 20 ? $attendanceDate->modify('+'.rand(1, 7).' days')->format('Y-m-d') : null,
            'type_of_service' => fake()->randomElement(['OPD', 'IPD']),
            'type_of_attendance' => fake()->randomElement(['EAE', 'ANC', 'PNC', 'FP', 'CWC', 'REV']),
            'specialty_attended' => fake()->randomElement(['General Medicine', 'Surgery', 'Pediatrics', 'OB/GYN', 'Orthopedics', 'ENT']),
            'attending_prescriber' => fake()->name(),
            'is_unbundled' => rand(0, 100) < 15,
            'is_pharmacy_included' => rand(0, 100) < 80,
            'primary_diagnosis_code' => fake()->regexify('[A-Z][0-9]{2}\.[0-9]'),
            'primary_diagnosis_description' => fake()->sentence(4),
            'secondary_diagnoses' => null,
            'c_drg_code' => fake()->optional()->regexify('[A-Z0-9]{5}'),
            'hin_number' => fake()->optional()->numerify('##########'),
            'total_claim_amount' => round($totalClaimAmount, 2),
            'approved_amount' => round($approvedAmount, 2),
            'patient_copay_amount' => round($patientCopayAmount, 2),
            'insurance_covered_amount' => round($insuranceCoveredAmount, 2),
            'status' => $status,
            'vetted_by' => $vettedBy,
            'vetted_at' => $vettedAt,
            'submitted_by' => $submittedBy,
            'submitted_at' => $submittedAt,
            'submission_date' => $submissionDate,
            'approval_date' => $approvalDate,
            'payment_date' => $paymentDate,
            'payment_amount' => round($paymentAmount, 2),
            'payment_reference' => $status === 'paid' || $status === 'partial' ? 'PAY'.str_pad(rand(1000, 9999), 10, '0', STR_PAD_LEFT) : null,
            'payment_recorded_by' => $status === 'paid' || $status === 'partial' ? $submittedBy : null,
            'rejection_reason' => $rejectionReason,
            'rejected_by' => $rejectedBy,
            'rejected_at' => $rejectedAt,
            'resubmission_count' => 0,
            'last_resubmitted_at' => null,
            'batch_reference' => $status === 'submitted' ? 'BATCH'.now()->format('Ymd').rand(100, 999) : null,
            'batch_submitted_at' => $submittedAt,
            'approved_by' => in_array($status, ['approved', 'paid', 'partial']) ? $submittedBy : null,
            'notes' => null,
            'created_at' => $createdAt,
            'updated_at' => $rejectedAt ?? $vettedAt ?? $createdAt,
        ]);

        // Create claim items
        foreach ($claimItems as $itemData) {
            // Create a charge first
            $charge = Charge::create([
                'patient_checkin_id' => $checkin->id,
                'prescription_id' => null,
                'insurance_claim_id' => $claim->id,
                'insurance_claim_item_id' => null,
                'service_type' => $itemData['item_type'],
                'service_code' => $itemData['code'],
                'description' => $itemData['description'],
                'amount' => $itemData['subtotal'],
                'insurance_tariff_amount' => $itemData['unit_tariff'],
                'charge_type' => $itemData['charge_type'],
                'status' => in_array($status, ['paid', 'partial']) ? 'paid' : 'pending',
                'is_insurance_claim' => true,
                'paid_amount' => in_array($status, ['paid', 'partial']) ? $itemData['patient_pays'] : 0,
                'insurance_covered_amount' => $itemData['insurance_pays'],
                'patient_copay_amount' => $itemData['patient_pays'],
                'charged_at' => $createdAt,
                'due_date' => null,
                'paid_at' => in_array($status, ['paid', 'partial']) ? $paymentDate : null,
                'metadata' => null,
                'created_by_type' => 'App\\Models\\User',
                'created_by_id' => 1,
                'is_emergency_override' => false,
                'notes' => null,
            ]);

            // Create claim item
            InsuranceClaimItem::create([
                'insurance_claim_id' => $claim->id,
                'charge_id' => $charge->id,
                'item_date' => $itemData['item_date'],
                'item_type' => $itemData['item_type'],
                'code' => $itemData['code'],
                'description' => $itemData['description'],
                'quantity' => $itemData['quantity'],
                'unit_tariff' => $itemData['unit_tariff'],
                'subtotal' => $itemData['subtotal'],
                'is_covered' => $itemData['is_covered'],
                'coverage_percentage' => $itemData['coverage_percentage'],
                'insurance_pays' => $itemData['insurance_pays'],
                'patient_pays' => $itemData['patient_pays'],
                'is_approved' => $itemData['is_approved'],
                'rejection_reason' => $itemData['rejection_reason'],
                'notes' => $itemData['notes'],
            ]);
        }
    }

    private function generateCashCharges(): void
    {
        // Generate cash charges for revenue comparison (charges without insurance)
        $patientsWithoutInsurance = Patient::whereDoesntHave('insurancePlans')->limit(20)->get();

        if ($patientsWithoutInsurance->isEmpty()) {
            // Create some patients without insurance
            for ($i = 0; $i < 20; $i++) {
                $patientsWithoutInsurance->push(Patient::factory()->create());
            }
        }

        // Generate charges over the last 6 months
        for ($monthOffset = 5; $monthOffset >= 0; $monthOffset--) {
            $monthStart = now()->subMonths($monthOffset)->startOfMonth();
            $monthEnd = now()->subMonths($monthOffset)->endOfMonth();

            // Generate 20-40 cash charges per month
            $chargesThisMonth = rand(20, 40);

            for ($i = 0; $i < $chargesThisMonth; $i++) {
                $patient = $patientsWithoutInsurance->random();
                $chargeDate = fake()->dateTimeBetween($monthStart, $monthEnd);

                // Create a checkin for cash patients
                $cashCheckin = \App\Models\PatientCheckin::create([
                    'patient_id' => $patient->id,
                    'department_id' => rand(1, 5), // Random department from seeded departments
                    'checked_in_by' => 1, // Default user
                    'checked_in_at' => $chargeDate,
                    'status' => 'completed',
                    'claim_check_code' => 'CSH'.str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT),
                    'consultation_completed_at' => (clone $chargeDate)->modify('+'.rand(1, 4).' hours'),
                ]);

                $serviceType = $this->serviceTypes[array_rand($this->serviceTypes)];
                $description = $serviceType['descriptions'][array_rand($serviceType['descriptions'])];

                $amount = fake()->randomFloat(2, 50, 1500);

                Charge::create([
                    'patient_checkin_id' => $cashCheckin->id,
                    'prescription_id' => null,
                    'insurance_claim_id' => null,
                    'insurance_claim_item_id' => null,
                    'service_type' => $serviceType['type'],
                    'service_code' => strtoupper(fake()->bothify('??###??')),
                    'description' => $description,
                    'amount' => $amount,
                    'insurance_tariff_amount' => null,
                    'charge_type' => $serviceType['charge_type'],
                    'status' => 'paid',
                    'is_insurance_claim' => false,
                    'paid_amount' => $amount,
                    'insurance_covered_amount' => 0,
                    'patient_copay_amount' => 0,
                    'charged_at' => $chargeDate,
                    'due_date' => null,
                    'paid_at' => $chargeDate,
                    'metadata' => null,
                    'created_by_type' => 'App\\Models\\User',
                    'created_by_id' => 1,
                    'is_emergency_override' => false,
                    'notes' => null,
                    'created_at' => $chargeDate,
                    'updated_at' => $chargeDate,
                ]);
            }
        }
    }

    private function weightedRandom(array $weights): string
    {
        $totalWeight = array_sum($weights);
        $random = rand(1, $totalWeight);

        $currentWeight = 0;
        foreach ($weights as $key => $weight) {
            $currentWeight += $weight;
            if ($random <= $currentWeight) {
                return $key;
            }
        }

        return array_key_first($weights);
    }
}
