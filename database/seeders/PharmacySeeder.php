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
