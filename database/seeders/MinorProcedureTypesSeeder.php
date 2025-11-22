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
        $procedureTypes = [
            [
                'name' => 'Wound Dressing',
                'code' => 'WD001',
                'category' => 'wound_care',
                'description' => 'Cleaning and dressing of wounds',
                'price' => 50.00,
                'is_active' => true,
            ],
            [
                'name' => 'Catheter Change (Urinary)',
                'code' => 'CC-URN',
                'category' => 'catheter',
                'description' => 'Removal and insertion of urinary catheter',
                'price' => 80.00,
                'is_active' => true,
            ],
            [
                'name' => 'Catheter Change (IV)',
                'code' => 'CC-IV',
                'category' => 'catheter',
                'description' => 'Removal and insertion of intravenous catheter',
                'price' => 70.00,
                'is_active' => true,
            ],
            [
                'name' => 'Suture Removal',
                'code' => 'SR001',
                'category' => 'wound_care',
                'description' => 'Removal of surgical sutures',
                'price' => 40.00,
                'is_active' => true,
            ],
            [
                'name' => 'Dressing Change',
                'code' => 'DC001',
                'category' => 'wound_care',
                'description' => 'Change of existing wound dressing',
                'price' => 45.00,
                'is_active' => true,
            ],
            [
                'name' => 'Injection (IM)',
                'code' => 'INJ-IM',
                'category' => 'injection',
                'description' => 'Intramuscular injection',
                'price' => 30.00,
                'is_active' => true,
            ],
            [
                'name' => 'Injection (IV)',
                'code' => 'INJ-IV',
                'category' => 'injection',
                'description' => 'Intravenous injection',
                'price' => 35.00,
                'is_active' => true,
            ],
            [
                'name' => 'Injection (SC)',
                'code' => 'INJ-SC',
                'category' => 'injection',
                'description' => 'Subcutaneous injection',
                'price' => 25.00,
                'is_active' => true,
            ],
            [
                'name' => 'Nebulization',
                'code' => 'NEB001',
                'category' => 'respiratory',
                'description' => 'Nebulizer treatment for respiratory conditions',
                'price' => 60.00,
                'is_active' => true,
            ],
            [
                'name' => 'Ear Syringing',
                'code' => 'ES001',
                'category' => 'ear_care',
                'description' => 'Irrigation of ear canal to remove wax',
                'price' => 55.00,
                'is_active' => true,
            ],
            [
                'name' => 'Nasogastric Tube Insertion',
                'code' => 'NGT001',
                'category' => 'tube_insertion',
                'description' => 'Insertion of nasogastric feeding tube',
                'price' => 75.00,
                'is_active' => true,
            ],
            [
                'name' => 'Blood Pressure Monitoring',
                'code' => 'BPM001',
                'category' => 'monitoring',
                'description' => 'Blood pressure check and monitoring',
                'price' => 0.00, // Uses department consultation_fee
                'is_active' => true,
            ],
            [
                'name' => 'Temperature Check',
                'code' => 'TC001',
                'category' => 'monitoring',
                'description' => 'Body temperature measurement',
                'price' => 0.00, // Uses department consultation_fee
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
