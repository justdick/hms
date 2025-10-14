<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentMethods = [
            [
                'name' => 'Cash',
                'code' => 'CASH',
                'description' => 'Payment by cash',
                'requires_reference' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Credit Card',
                'code' => 'CREDIT',
                'description' => 'Payment by credit card',
                'requires_reference' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Debit Card',
                'code' => 'DEBIT',
                'description' => 'Payment by debit card',
                'requires_reference' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Bank Transfer',
                'code' => 'BANK',
                'description' => 'Payment by bank transfer',
                'requires_reference' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Mobile Payment',
                'code' => 'MOBILE',
                'description' => 'Payment via mobile payment services',
                'requires_reference' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Insurance',
                'code' => 'INSURANCE',
                'description' => 'Payment through insurance coverage',
                'requires_reference' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Check',
                'code' => 'CHECK',
                'description' => 'Payment by check',
                'requires_reference' => true,
                'is_active' => true,
            ],
        ];

        foreach ($paymentMethods as $method) {
            PaymentMethod::create($method);
        }
    }
}
