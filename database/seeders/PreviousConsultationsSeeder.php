<?php

namespace Database\Seeders;

use App\Models\Consultation;
use App\Models\ConsultationDiagnosis;
use App\Models\Department;
use App\Models\Diagnosis;
use App\Models\LabOrder;
use App\Models\LabService;
use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\Prescription;
use App\Models\User;
use App\Models\VitalSign;
use Illuminate\Database\Seeder;

class PreviousConsultationsSeeder extends Seeder
{
    public function run(): void
    {
        // Get first patient in the system
        $patient = Patient::first();

        if (! $patient) {
            $this->command->warn('No patients found. Please run DatabaseSeeder first.');

            return;
        }

        // Get a doctor
        $doctor = User::whereHas('roles', function ($query) {
            $query->where('name', 'doctor');
        })->first();

        if (! $doctor) {
            $doctor = User::first();
        }

        // Get departments
        $departments = Department::active()->limit(3)->get();

        if ($departments->isEmpty()) {
            $this->command->warn('No departments found. Please run DatabaseSeeder first.');

            return;
        }

        // Get lab services
        $labServices = LabService::active()->limit(5)->get();

        $this->command->info("Creating previous consultations for patient: {$patient->first_name} {$patient->last_name}");

        // Create 5 previous completed consultations
        $consultationData = [
            [
                'date' => now()->subDays(30),
                'presenting_complaint' => 'Annual physical examination',
                'history_presenting_complaint' => 'Patient reports feeling generally well. No major concerns. Requests routine health screening.',
                'on_direct_questioning' => 'No recent illnesses, no medication changes. Systems review negative.',
                'examination_findings' => 'Physical examination reveals normal findings. Patient appears healthy and well-nourished. All systems within normal limits.',
                'assessment' => 'Healthy adult. Routine screening examination.',
                'plan' => 'Continue current lifestyle. Schedule follow-up in 1 year. Routine labs ordered.',
                'diagnoses' => [
                    ['code' => 'Z00.00', 'description' => 'Encounter for general adult medical examination without abnormal findings', 'primary' => true],
                ],
                'prescriptions' => [],
                'vitals' => [
                    'temperature' => 98.6,
                    'bp_systolic' => 120,
                    'bp_diastolic' => 80,
                    'pulse' => 72,
                    'respiratory' => 16,
                ],
            ],
            [
                'date' => now()->subDays(60),
                'presenting_complaint' => 'Persistent cough for 2 weeks',
                'history_presenting_complaint' => 'Dry cough started 2 weeks ago, gradually worsening. Initially intermittent, now constant throughout the day. No sputum production.',
                'on_direct_questioning' => 'No fever, but reports fatigue. Denies chest pain, shortness of breath, or hemoptysis. No recent travel or sick contacts.',
                'examination_findings' => 'Temp 98.4°F. Lungs clear to auscultation bilaterally. No wheezing or crackles. Throat slightly erythematous, no exudate.',
                'assessment' => 'Acute bronchitis, likely viral etiology.',
                'plan' => 'Symptomatic treatment. Cough suppressant prescribed. Return if worsening or fever develops.',
                'diagnoses' => [
                    ['code' => 'J20.9', 'description' => 'Acute bronchitis, unspecified', 'primary' => true],
                ],
                'prescriptions' => [
                    [
                        'medication' => 'Dextromethorphan 15mg',
                        'dosage' => '15mg',
                        'frequency' => 'Three times daily (TID)',
                        'duration' => '7 days',
                        'instructions' => 'Take with food. May cause drowsiness.',
                    ],
                    [
                        'medication' => 'Guaifenesin 200mg',
                        'dosage' => '200mg',
                        'frequency' => 'Twice daily (BID)',
                        'duration' => '7 days',
                        'instructions' => 'Drink plenty of water.',
                    ],
                ],
                'vitals' => [
                    'temperature' => 98.4,
                    'bp_systolic' => 118,
                    'bp_diastolic' => 78,
                    'pulse' => 76,
                    'respiratory' => 18,
                ],
            ],
            [
                'date' => now()->subDays(90),
                'presenting_complaint' => 'Follow-up for hypertension',
                'history_presenting_complaint' => 'Patient reports compliance with Lisinopril 10mg daily. Blood pressure readings at home averaging 130/85 over the past month.',
                'on_direct_questioning' => 'No dizziness, headaches, or visual changes. No chest pain or palpitations. Sleeping well.',
                'examination_findings' => 'BP 132/84 mmHg, HR 68 bpm regular. CVS: S1 S2 normal, no murmurs. No peripheral edema. Cardiovascular exam unremarkable.',
                'assessment' => 'Hypertension, controlled on current medications.',
                'plan' => 'Continue current medication regimen. Follow-up in 3 months. Monitor home BP readings.',
                'diagnoses' => [
                    ['code' => 'I10', 'description' => 'Essential (primary) hypertension', 'primary' => true],
                ],
                'prescriptions' => [
                    [
                        'medication' => 'Lisinopril 10mg',
                        'dosage' => '10mg',
                        'frequency' => 'Once daily',
                        'duration' => '90 days',
                        'instructions' => 'Take in the morning with or without food.',
                    ],
                ],
                'vitals' => [
                    'temperature' => 98.7,
                    'bp_systolic' => 132,
                    'bp_diastolic' => 84,
                    'pulse' => 68,
                    'respiratory' => 16,
                ],
            ],
            [
                'date' => now()->subDays(120),
                'presenting_complaint' => 'Acute back pain',
                'history_presenting_complaint' => 'Lower back pain started 3 days ago after lifting heavy boxes at work. Pain is sharp, 7/10 severity, worse with movement. Radiates to left leg posteriorly.',
                'on_direct_questioning' => 'No numbness or tingling. No bowel or bladder dysfunction. No fever or weight loss. Previous episode 2 years ago resolved with rest.',
                'examination_findings' => 'Tenderness to palpation over L4-L5 region. Paraspinal muscle spasm noted. Straight leg raise test positive on left at 45 degrees. Motor strength 5/5 bilateral lower extremities. Sensory exam intact. Deep tendon reflexes 2+ and symmetric.',
                'assessment' => 'Acute lumbar strain with possible radiculopathy.',
                'plan' => 'NSAIDs prescribed. Physical therapy referral. Avoid heavy lifting. Return if symptoms worsen or numbness develops.',
                'diagnoses' => [
                    ['code' => 'M54.5', 'description' => 'Low back pain', 'primary' => true],
                    ['code' => 'M54.16', 'description' => 'Radiculopathy, lumbar region', 'primary' => false],
                ],
                'prescriptions' => [
                    [
                        'medication' => 'Ibuprofen 400mg',
                        'dosage' => '400mg',
                        'frequency' => 'Three times daily (TID)',
                        'duration' => '10 days',
                        'instructions' => 'Take with food to reduce stomach upset.',
                    ],
                    [
                        'medication' => 'Cyclobenzaprine 5mg',
                        'dosage' => '5mg',
                        'frequency' => 'At bedtime',
                        'duration' => '7 days',
                        'instructions' => 'May cause drowsiness. Do not drive or operate machinery.',
                    ],
                ],
                'vitals' => [
                    'temperature' => 98.5,
                    'bp_systolic' => 125,
                    'bp_diastolic' => 82,
                    'pulse' => 74,
                    'respiratory' => 16,
                ],
            ],
            [
                'date' => now()->subDays(150),
                'presenting_complaint' => 'Routine diabetes follow-up',
                'history_presenting_complaint' => 'Patient reports good glycemic control with home blood glucose monitoring. Readings typically 110-140 mg/dL fasting, 140-180 mg/dL post-prandial. Compliant with Metformin 500mg BID and dietary modifications.',
                'on_direct_questioning' => 'No polyuria, polydipsia, or polyphagia. No visual changes. No numbness or tingling in extremities. Denies chest pain or shortness of breath.',
                'examination_findings' => 'Weight stable at 185 lbs. Fasting glucose today 125 mg/dL. Foot exam: pulses present bilaterally, monofilament test normal, no ulcers or calluses. Fundoscopic exam: no retinopathy noted.',
                'assessment' => 'Type 2 diabetes mellitus, controlled.',
                'plan' => 'Continue current medications. HbA1c ordered. Follow-up in 3 months. Continue diet and exercise regimen.',
                'diagnoses' => [
                    ['code' => 'E11.9', 'description' => 'Type 2 diabetes mellitus without complications', 'primary' => true],
                ],
                'prescriptions' => [
                    [
                        'medication' => 'Metformin 500mg',
                        'dosage' => '500mg',
                        'frequency' => 'Twice daily (BID)',
                        'duration' => '90 days',
                        'instructions' => 'Take with meals. May cause GI upset initially.',
                    ],
                ],
                'vitals' => [
                    'temperature' => 98.6,
                    'bp_systolic' => 128,
                    'bp_diastolic' => 80,
                    'pulse' => 70,
                    'respiratory' => 16,
                ],
            ],
        ];

        foreach ($consultationData as $index => $data) {
            // Create patient check-in
            $checkin = PatientCheckin::create([
                'patient_id' => $patient->id,
                'department_id' => $departments->random()->id,
                'checked_in_at' => $data['date'],
                'checked_in_by' => $doctor->id,
                'status' => 'completed',
                'consultation_started_at' => $data['date']->addMinutes(10),
                'consultation_completed_at' => $data['date']->addMinutes(30),
            ]);

            // Create vital signs
            VitalSign::create([
                'patient_id' => $patient->id,
                'patient_checkin_id' => $checkin->id,
                'temperature' => $data['vitals']['temperature'],
                'blood_pressure_systolic' => $data['vitals']['bp_systolic'],
                'blood_pressure_diastolic' => $data['vitals']['bp_diastolic'],
                'pulse_rate' => $data['vitals']['pulse'],
                'respiratory_rate' => $data['vitals']['respiratory'],
                'recorded_at' => $data['date']->addMinutes(5),
                'recorded_by' => $doctor->id,
            ]);

            // Create consultation
            $consultation = Consultation::create([
                'patient_checkin_id' => $checkin->id,
                'doctor_id' => $doctor->id,
                'started_at' => $data['date']->addMinutes(10),
                'completed_at' => $data['date']->addMinutes(30),
                'status' => 'completed',
                'presenting_complaint' => $data['presenting_complaint'],
                'history_presenting_complaint' => $data['history_presenting_complaint'],
                'on_direct_questioning' => $data['on_direct_questioning'],
                'examination_findings' => $data['examination_findings'],
                'assessment_notes' => $data['assessment'],
                'plan_notes' => $data['plan'],
            ]);

            // Create diagnoses
            foreach ($data['diagnoses'] as $diagnosisData) {
                // Find or create the diagnosis
                $diagnosis = Diagnosis::firstOrCreate(
                    ['code' => $diagnosisData['code']],
                    ['diagnosis' => $diagnosisData['description']]
                );

                ConsultationDiagnosis::create([
                    'consultation_id' => $consultation->id,
                    'diagnosis_id' => $diagnosis->id,
                    'type' => $diagnosisData['primary'] ? 'principal' : 'provisional',
                ]);
            }

            // Create prescriptions
            foreach ($data['prescriptions'] as $prescription) {
                Prescription::create([
                    'consultation_id' => $consultation->id,
                    'medication_name' => $prescription['medication'],
                    'dosage' => $prescription['dosage'],
                    'frequency' => $prescription['frequency'],
                    'duration' => $prescription['duration'],
                    'instructions' => $prescription['instructions'],
                    'status' => 'prescribed',
                ]);
            }

            // Add lab orders with detailed results to some consultations
            if ($index === 0 || $index === 4) {
                if ($labServices->isNotEmpty()) {
                    // For the annual physical (index 0), add CBC and CMP
                    if ($index === 0) {
                        // CBC with detailed results
                        $cbcService = $labServices->firstWhere('code', 'CBC') ?? $labServices->first();
                        LabOrder::create([
                            'consultation_id' => $consultation->id,
                            'lab_service_id' => $cbcService->id,
                            'status' => 'completed',
                            'priority' => 'routine',
                            'ordered_at' => $data['date']->addMinutes(25),
                            'ordered_by' => $doctor->id,
                            'sample_collected_at' => $data['date']->addMinutes(40),
                            'result_entered_at' => $data['date']->addHours(2),
                            'result_values' => [
                                'WBC' => ['value' => 7.2, 'unit' => '10^3/µL', 'range' => '4.0-11.0', 'flag' => 'normal'],
                                'RBC' => ['value' => 4.8, 'unit' => '10^6/µL', 'range' => '4.5-5.5', 'flag' => 'normal'],
                                'Hemoglobin' => ['value' => 14.5, 'unit' => 'g/dL', 'range' => '13.5-17.5', 'flag' => 'normal'],
                                'Hematocrit' => ['value' => 43.2, 'unit' => '%', 'range' => '38-50', 'flag' => 'normal'],
                                'Platelets' => ['value' => 245, 'unit' => '10^3/µL', 'range' => '150-400', 'flag' => 'normal'],
                            ],
                            'result_notes' => 'Complete blood count shows all values within normal limits. No evidence of anemia or infection.',
                        ]);

                        // Lipid Panel with some abnormal values
                        $lipidService = $labServices->skip(1)->first() ?? $labServices->first();
                        LabOrder::create([
                            'consultation_id' => $consultation->id,
                            'lab_service_id' => $lipidService->id,
                            'status' => 'completed',
                            'priority' => 'routine',
                            'ordered_at' => $data['date']->addMinutes(25),
                            'ordered_by' => $doctor->id,
                            'sample_collected_at' => $data['date']->addMinutes(40),
                            'result_entered_at' => $data['date']->addHours(2),
                            'result_values' => [
                                'Total Cholesterol' => ['value' => 215, 'unit' => 'mg/dL', 'range' => '<200', 'flag' => 'high'],
                                'LDL Cholesterol' => ['value' => 145, 'unit' => 'mg/dL', 'range' => '<100', 'flag' => 'high'],
                                'HDL Cholesterol' => ['value' => 48, 'unit' => 'mg/dL', 'range' => '>40', 'flag' => 'normal'],
                                'Triglycerides' => ['value' => 110, 'unit' => 'mg/dL', 'range' => '<150', 'flag' => 'normal'],
                            ],
                            'result_notes' => 'Lipid panel shows elevated total cholesterol and LDL. Recommend dietary modifications and possible statin therapy. Recheck in 3 months.',
                        ]);
                    }

                    // For diabetes follow-up (index 4), add HbA1c and glucose
                    if ($index === 4) {
                        $hba1cService = $labServices->skip(2)->first() ?? $labServices->first();
                        LabOrder::create([
                            'consultation_id' => $consultation->id,
                            'lab_service_id' => $hba1cService->id,
                            'status' => 'completed',
                            'priority' => 'routine',
                            'ordered_at' => $data['date']->addMinutes(25),
                            'ordered_by' => $doctor->id,
                            'sample_collected_at' => $data['date']->addMinutes(40),
                            'result_entered_at' => $data['date']->addHours(2),
                            'result_values' => [
                                'HbA1c' => ['value' => 6.8, 'unit' => '%', 'range' => '<5.7', 'flag' => 'high'],
                                'Fasting Glucose' => ['value' => 125, 'unit' => 'mg/dL', 'range' => '70-100', 'flag' => 'high'],
                            ],
                            'result_notes' => 'HbA1c of 6.8% indicates fair glycemic control. Continue current medication regimen. Reinforce dietary compliance.',
                        ]);
                    }
                }
            }

            $this->command->info("Created consultation #{$consultation->id} - {$data['presenting_complaint']}");
        }

        $this->command->info('✓ Previous consultations seeded successfully!');
    }
}
