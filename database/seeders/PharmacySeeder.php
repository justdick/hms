<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PharmacySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create suppliers
        $suppliers = [
            ['name' => 'MedSupply Co.', 'supplier_code' => 'MS001', 'is_active' => true],
            ['name' => 'PharmaCorp', 'supplier_code' => 'PC001', 'is_active' => true],
            ['name' => 'HealthDistributors Ltd', 'supplier_code' => 'HD001', 'is_active' => true],
        ];

        foreach ($suppliers as $supplierData) {
            \App\Models\Supplier::create($supplierData);
        }

        // Create drugs
        $drugs = [
            // Analgesics & Antipyretics
            [
                'name' => 'Paracetamol',
                'generic_name' => 'Acetaminophen',
                'drug_code' => 'PCM500',
                'category' => 'analgesics',
                'form' => 'tablet',
                'strength' => '500mg',
                'unit_price' => 0.50,
                'unit_type' => 'piece',
                'minimum_stock_level' => 100,
                'is_active' => true,
            ],
            [
                'name' => 'Ibuprofen',
                'generic_name' => 'Ibuprofen',
                'drug_code' => 'IBU400',
                'category' => 'analgesics',
                'form' => 'tablet',
                'strength' => '400mg',
                'unit_price' => 0.80,
                'unit_type' => 'piece',
                'minimum_stock_level' => 100,
                'is_active' => true,
            ],
            [
                'name' => 'Diclofenac',
                'generic_name' => 'Diclofenac Sodium',
                'drug_code' => 'DCL50',
                'category' => 'analgesics',
                'form' => 'tablet',
                'strength' => '50mg',
                'unit_price' => 1.00,
                'unit_type' => 'piece',
                'minimum_stock_level' => 80,
                'is_active' => true,
            ],
            [
                'name' => 'Tramadol',
                'generic_name' => 'Tramadol HCl',
                'drug_code' => 'TRM50',
                'category' => 'analgesics',
                'form' => 'capsule',
                'strength' => '50mg',
                'unit_price' => 2.50,
                'unit_type' => 'piece',
                'minimum_stock_level' => 50,
                'is_active' => true,
            ],

            // Antibiotics
            [
                'name' => 'Amoxicillin',
                'generic_name' => 'Amoxicillin',
                'drug_code' => 'AMX500',
                'category' => 'antibiotics',
                'form' => 'capsule',
                'strength' => '500mg',
                'unit_price' => 1.20,
                'unit_type' => 'piece',
                'minimum_stock_level' => 50,
                'is_active' => true,
            ],
            [
                'name' => 'Ciprofloxacin',
                'generic_name' => 'Ciprofloxacin',
                'drug_code' => 'CIP500',
                'category' => 'antibiotics',
                'form' => 'tablet',
                'strength' => '500mg',
                'unit_price' => 1.50,
                'unit_type' => 'piece',
                'minimum_stock_level' => 50,
                'is_active' => true,
            ],
            [
                'name' => 'Azithromycin',
                'generic_name' => 'Azithromycin',
                'drug_code' => 'AZT500',
                'category' => 'antibiotics',
                'form' => 'tablet',
                'strength' => '500mg',
                'unit_price' => 2.00,
                'unit_type' => 'piece',
                'minimum_stock_level' => 40,
                'is_active' => true,
            ],
            [
                'name' => 'Metronidazole',
                'generic_name' => 'Metronidazole',
                'drug_code' => 'MTZ400',
                'category' => 'antibiotics',
                'form' => 'tablet',
                'strength' => '400mg',
                'unit_price' => 0.90,
                'unit_type' => 'piece',
                'minimum_stock_level' => 60,
                'is_active' => true,
            ],
            [
                'name' => 'Ceftriaxone',
                'generic_name' => 'Ceftriaxone Sodium',
                'drug_code' => 'CFT1G',
                'category' => 'antibiotics',
                'form' => 'injection',
                'strength' => '1g',
                'unit_price' => 5.00,
                'unit_type' => 'vial',
                'minimum_stock_level' => 30,
                'is_active' => true,
            ],

            // Cardiovascular
            [
                'name' => 'Amlodipine',
                'generic_name' => 'Amlodipine Besylate',
                'drug_code' => 'AML5',
                'category' => 'cardiovascular',
                'form' => 'tablet',
                'strength' => '5mg',
                'unit_price' => 0.75,
                'unit_type' => 'piece',
                'minimum_stock_level' => 100,
                'is_active' => true,
            ],
            [
                'name' => 'Enalapril',
                'generic_name' => 'Enalapril Maleate',
                'drug_code' => 'ENL5',
                'category' => 'cardiovascular',
                'form' => 'tablet',
                'strength' => '5mg',
                'unit_price' => 0.80,
                'unit_type' => 'piece',
                'minimum_stock_level' => 80,
                'is_active' => true,
            ],
            [
                'name' => 'Losartan',
                'generic_name' => 'Losartan Potassium',
                'drug_code' => 'LST50',
                'category' => 'cardiovascular',
                'form' => 'tablet',
                'strength' => '50mg',
                'unit_price' => 1.20,
                'unit_type' => 'piece',
                'minimum_stock_level' => 80,
                'is_active' => true,
            ],

            // Diabetes
            [
                'name' => 'Metformin',
                'generic_name' => 'Metformin HCl',
                'drug_code' => 'MET500',
                'category' => 'diabetes',
                'form' => 'tablet',
                'strength' => '500mg',
                'unit_price' => 0.60,
                'unit_type' => 'piece',
                'minimum_stock_level' => 100,
                'is_active' => true,
            ],
            [
                'name' => 'Glibenclamide',
                'generic_name' => 'Glibenclamide',
                'drug_code' => 'GLB5',
                'category' => 'diabetes',
                'form' => 'tablet',
                'strength' => '5mg',
                'unit_price' => 0.50,
                'unit_type' => 'piece',
                'minimum_stock_level' => 80,
                'is_active' => true,
            ],
            [
                'name' => 'Insulin (Regular)',
                'generic_name' => 'Human Insulin',
                'drug_code' => 'INS100',
                'category' => 'diabetes',
                'form' => 'injection',
                'strength' => '100IU/ml',
                'unit_price' => 15.00,
                'unit_type' => 'vial',
                'minimum_stock_level' => 20,
                'is_active' => true,
            ],

            // Respiratory
            [
                'name' => 'Cough Syrup',
                'generic_name' => 'Dextromethorphan',
                'drug_code' => 'CS100',
                'category' => 'respiratory',
                'form' => 'syrup',
                'strength' => '15mg/5ml',
                'unit_price' => 3.50,
                'unit_type' => 'bottle',
                'minimum_stock_level' => 20,
                'is_active' => true,
            ],
            [
                'name' => 'Salbutamol Inhaler',
                'generic_name' => 'Salbutamol',
                'drug_code' => 'SBT100',
                'category' => 'respiratory',
                'form' => 'inhaler',
                'strength' => '100mcg',
                'unit_price' => 8.00,
                'unit_type' => 'piece',
                'minimum_stock_level' => 15,
                'is_active' => true,
            ],
            [
                'name' => 'Prednisolone',
                'generic_name' => 'Prednisolone',
                'drug_code' => 'PRD5',
                'category' => 'respiratory',
                'form' => 'tablet',
                'strength' => '5mg',
                'unit_price' => 0.70,
                'unit_type' => 'piece',
                'minimum_stock_level' => 60,
                'is_active' => true,
            ],

            // Gastrointestinal
            [
                'name' => 'Omeprazole',
                'generic_name' => 'Omeprazole',
                'drug_code' => 'OMP20',
                'category' => 'gastrointestinal',
                'form' => 'capsule',
                'strength' => '20mg',
                'unit_price' => 1.00,
                'unit_type' => 'piece',
                'minimum_stock_level' => 80,
                'is_active' => true,
            ],
            [
                'name' => 'Ranitidine',
                'generic_name' => 'Ranitidine HCl',
                'drug_code' => 'RNT150',
                'category' => 'gastrointestinal',
                'form' => 'tablet',
                'strength' => '150mg',
                'unit_price' => 0.60,
                'unit_type' => 'piece',
                'minimum_stock_level' => 80,
                'is_active' => true,
            ],
            [
                'name' => 'Loperamide',
                'generic_name' => 'Loperamide HCl',
                'drug_code' => 'LOP2',
                'category' => 'gastrointestinal',
                'form' => 'capsule',
                'strength' => '2mg',
                'unit_price' => 0.80,
                'unit_type' => 'piece',
                'minimum_stock_level' => 40,
                'is_active' => true,
            ],

            // Respiratory (Antihistamines)
            [
                'name' => 'Cetirizine',
                'generic_name' => 'Cetirizine HCl',
                'drug_code' => 'CTZ10',
                'category' => 'respiratory',
                'form' => 'tablet',
                'strength' => '10mg',
                'unit_price' => 0.50,
                'unit_type' => 'piece',
                'minimum_stock_level' => 60,
                'is_active' => true,
            ],
            [
                'name' => 'Chlorpheniramine',
                'generic_name' => 'Chlorpheniramine Maleate',
                'drug_code' => 'CHL4',
                'category' => 'respiratory',
                'form' => 'tablet',
                'strength' => '4mg',
                'unit_price' => 0.30,
                'unit_type' => 'piece',
                'minimum_stock_level' => 60,
                'is_active' => true,
            ],

            // Vitamins & Supplements
            [
                'name' => 'Vitamin B Complex',
                'generic_name' => 'Vitamin B Complex',
                'drug_code' => 'VBC',
                'category' => 'vitamins',
                'form' => 'tablet',
                'strength' => 'Standard',
                'unit_price' => 0.40,
                'unit_type' => 'piece',
                'minimum_stock_level' => 80,
                'is_active' => true,
            ],
            [
                'name' => 'Folic Acid',
                'generic_name' => 'Folic Acid',
                'drug_code' => 'FOL5',
                'category' => 'vitamins',
                'form' => 'tablet',
                'strength' => '5mg',
                'unit_price' => 0.30,
                'unit_type' => 'piece',
                'minimum_stock_level' => 60,
                'is_active' => true,
            ],
            [
                'name' => 'Ferrous Sulfate',
                'generic_name' => 'Ferrous Sulfate',
                'drug_code' => 'FER200',
                'category' => 'vitamins',
                'form' => 'tablet',
                'strength' => '200mg',
                'unit_price' => 0.40,
                'unit_type' => 'piece',
                'minimum_stock_level' => 80,
                'is_active' => true,
            ],

            // Cardiovascular (IV Solutions & Emergency)
            [
                'name' => 'Normal Saline',
                'generic_name' => 'Sodium Chloride 0.9%',
                'drug_code' => 'NS500',
                'category' => 'cardiovascular',
                'form' => 'injection',
                'strength' => '500ml',
                'unit_price' => 2.50,
                'unit_type' => 'bottle',
                'minimum_stock_level' => 100,
                'is_active' => true,
            ],
            [
                'name' => 'Dextrose 5%',
                'generic_name' => 'Dextrose 5% in Water',
                'drug_code' => 'D5W500',
                'category' => 'cardiovascular',
                'form' => 'injection',
                'strength' => '500ml',
                'unit_price' => 2.80,
                'unit_type' => 'bottle',
                'minimum_stock_level' => 100,
                'is_active' => true,
            ],
            [
                'name' => 'Adrenaline',
                'generic_name' => 'Epinephrine',
                'drug_code' => 'ADR1',
                'category' => 'cardiovascular',
                'form' => 'injection',
                'strength' => '1mg/ml',
                'unit_price' => 3.00,
                'unit_type' => 'vial',
                'minimum_stock_level' => 30,
                'is_active' => true,
            ],
        ];

        foreach ($drugs as $drugData) {
            \App\Models\Drug::create($drugData);
        }

        // Create drug batches
        $drug1 = \App\Models\Drug::where('drug_code', 'PCM500')->first();
        $drug2 = \App\Models\Drug::where('drug_code', 'AMX500')->first();
        $drug3 = \App\Models\Drug::where('drug_code', 'CS100')->first();
        $supplier = \App\Models\Supplier::first();

        $batches = [
            [
                'drug_id' => $drug1->id,
                'supplier_id' => $supplier->id,
                'batch_number' => 'PCM001',
                'expiry_date' => now()->addMonths(18),
                'quantity_received' => 500,
                'quantity_remaining' => 45, // Low stock
                'cost_per_unit' => 0.30,
                'selling_price_per_unit' => 0.50,
                'received_date' => now()->subDays(30),
            ],
            [
                'drug_id' => $drug2->id,
                'supplier_id' => $supplier->id,
                'batch_number' => 'AMX001',
                'expiry_date' => now()->addDays(20), // Expiring soon
                'quantity_received' => 200,
                'quantity_remaining' => 150,
                'cost_per_unit' => 0.80,
                'selling_price_per_unit' => 1.20,
                'received_date' => now()->subDays(45),
            ],
            [
                'drug_id' => $drug3->id,
                'supplier_id' => $supplier->id,
                'batch_number' => 'CS001',
                'expiry_date' => now()->addYears(2),
                'quantity_received' => 100,
                'quantity_remaining' => 85,
                'cost_per_unit' => 2.50,
                'selling_price_per_unit' => 3.50,
                'received_date' => now()->subDays(15),
            ],
        ];

        foreach ($batches as $batchData) {
            \App\Models\DrugBatch::create($batchData);
        }
    }
}
