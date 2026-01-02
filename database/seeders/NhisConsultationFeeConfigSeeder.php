<?php

namespace Database\Seeders;

use App\Models\BillingConfiguration;
use Illuminate\Database\Seeder;

class NhisConsultationFeeConfigSeeder extends Seeder
{
    /**
     * Seed the NHIS consultation fee configuration.
     */
    public function run(): void
    {
        BillingConfiguration::setValue(
            key: 'nhis_consultation_fee_once_per_lifetime',
            value: true,
            category: 'nhis',
            description: 'When enabled, NHIS patients are only charged consultation fee once per lifetime (not per visit).'
        );
    }
}
