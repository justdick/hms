<?php

namespace Database\Seeders;

use App\Models\MinorProcedureType;
use Illuminate\Database\Seeder;

class MinorProcedureTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Pricing Model:
        // - Department consultation_fee: Charged once at check-in (if set)
        // - Procedure price: Additional charge per procedure (if set)
        // - Set procedure price = 0 for procedures covered by consultation fee only
        // - Type: 'minor' for OPD procedures, 'major' for theatre procedures
        $procedureTypes = [
            // Minor Procedures (OPD)
            [
                'name' => 'Wound Dressing',
                'code' => 'WD001',
                'category' => 'wound_care',
                'type' => 'minor',
                'description' => 'Cleaning and dressing of wounds',
                'price' => 50.00,
                'is_active' => true,
            ],
            [
                'name' => 'Catheter Change (Urinary)',
                'code' => 'CC-URN',
                'category' => 'catheter',
                'type' => 'minor',
                'description' => 'Removal and insertion of urinary catheter',
                'price' => 80.00,
                'is_active' => true,
            ],
            [
                'name' => 'Catheter Change (IV)',
                'code' => 'CC-IV',
                'category' => 'catheter',
                'type' => 'minor',
                'description' => 'Removal and insertion of intravenous catheter',
                'price' => 70.00,
                'is_active' => true,
            ],
            [
                'name' => 'Suture Removal',
                'code' => 'SR001',
                'category' => 'wound_care',
                'type' => 'minor',
                'description' => 'Removal of surgical sutures',
                'price' => 40.00,
                'is_active' => true,
            ],
            [
                'name' => 'Dressing Change',
                'code' => 'DC001',
                'category' => 'wound_care',
                'type' => 'minor',
                'description' => 'Change of existing wound dressing',
                'price' => 45.00,
                'is_active' => true,
            ],
            [
                'name' => 'Injection (IM)',
                'code' => 'INJ-IM',
                'category' => 'injection',
                'type' => 'minor',
                'description' => 'Intramuscular injection',
                'price' => 30.00,
                'is_active' => true,
            ],
            [
                'name' => 'Injection (IV)',
                'code' => 'INJ-IV',
                'category' => 'injection',
                'type' => 'minor',
                'description' => 'Intravenous injection',
                'price' => 35.00,
                'is_active' => true,
            ],
            [
                'name' => 'Injection (SC)',
                'code' => 'INJ-SC',
                'category' => 'injection',
                'type' => 'minor',
                'description' => 'Subcutaneous injection',
                'price' => 25.00,
                'is_active' => true,
            ],
            [
                'name' => 'Nebulization',
                'code' => 'NEB001',
                'category' => 'respiratory',
                'type' => 'minor',
                'description' => 'Nebulizer treatment for respiratory conditions',
                'price' => 60.00,
                'is_active' => true,
            ],
            [
                'name' => 'Ear Syringing',
                'code' => 'ES001',
                'category' => 'ear_care',
                'type' => 'minor',
                'description' => 'Irrigation of ear canal to remove wax',
                'price' => 55.00,
                'is_active' => true,
            ],
            [
                'name' => 'Nasogastric Tube Insertion',
                'code' => 'NGT001',
                'category' => 'tube_insertion',
                'type' => 'minor',
                'description' => 'Insertion of nasogastric feeding tube',
                'price' => 75.00,
                'is_active' => true,
            ],
            [
                'name' => 'Blood Pressure Monitoring',
                'code' => 'BPM001',
                'category' => 'monitoring',
                'type' => 'minor',
                'description' => 'Blood pressure check and monitoring',
                'price' => 0.00, // Uses department consultation_fee
                'is_active' => true,
            ],
            [
                'name' => 'Temperature Check',
                'code' => 'TC001',
                'category' => 'monitoring',
                'type' => 'minor',
                'description' => 'Body temperature measurement',
                'price' => 0.00, // Uses department consultation_fee
                'is_active' => true,
            ],

            // Major Procedures (Theatre)
            [
                'name' => 'Appendectomy',
                'code' => 'APPEN001',
                'category' => 'general_surgery',
                'type' => 'major',
                'description' => 'Surgical removal of the appendix',
                'price' => 2500.00,
                'is_active' => true,
            ],
            [
                'name' => 'Cesarean Section',
                'code' => 'CS001',
                'category' => 'obstetrics',
                'type' => 'major',
                'description' => 'Surgical delivery of baby through abdominal incision',
                'price' => 3000.00,
                'is_active' => true,
            ],
            [
                'name' => 'Hernia Repair',
                'code' => 'HERN001',
                'category' => 'general_surgery',
                'type' => 'major',
                'description' => 'Surgical repair of hernia',
                'price' => 2200.00,
                'is_active' => true,
            ],
            [
                'name' => 'Cholecystectomy',
                'code' => 'CHOL001',
                'category' => 'general_surgery',
                'type' => 'major',
                'description' => 'Surgical removal of gallbladder',
                'price' => 2800.00,
                'is_active' => true,
            ],
            [
                'name' => 'Hysterectomy',
                'code' => 'HYST001',
                'category' => 'gynecology',
                'type' => 'major',
                'description' => 'Surgical removal of uterus',
                'price' => 3500.00,
                'is_active' => true,
            ],
            [
                'name' => 'Fracture Fixation',
                'code' => 'FRAC001',
                'category' => 'orthopedics',
                'type' => 'major',
                'description' => 'Surgical fixation of bone fracture',
                'price' => 3200.00,
                'is_active' => true,
            ],
            [
                'name' => 'Thyroidectomy',
                'code' => 'THYR001',
                'category' => 'general_surgery',
                'type' => 'major',
                'description' => 'Surgical removal of thyroid gland',
                'price' => 3000.00,
                'is_active' => true,
            ],
            [
                'name' => 'Mastectomy',
                'code' => 'MAST001',
                'category' => 'general_surgery',
                'type' => 'major',
                'description' => 'Surgical removal of breast tissue',
                'price' => 3500.00,
                'is_active' => true,
            ],
            [
                'name' => 'Laparotomy',
                'code' => 'LAP001',
                'category' => 'general_surgery',
                'type' => 'major',
                'description' => 'Exploratory abdominal surgery',
                'price' => 2800.00,
                'is_active' => true,
            ],
            [
                'name' => 'Prostatectomy',
                'code' => 'PROS001',
                'category' => 'urology',
                'type' => 'major',
                'description' => 'Surgical removal of prostate gland',
                'price' => 3500.00,
                'is_active' => true,
            ],
        ];

        foreach ($procedureTypes as $procedureType) {
            MinorProcedureType::updateOrCreate(
                ['code' => $procedureType['code']],
                $procedureType
            );
        }
    }
}
