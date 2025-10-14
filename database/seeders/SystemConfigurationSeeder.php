<?php

namespace Database\Seeders;

use App\Models\SystemConfiguration;
use Illuminate\Database\Seeder;

class SystemConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Patient Number Configuration
        $patientNumberConfigs = [
            [
                'key' => 'patient_number_prefix',
                'value' => 'PAT',
                'type' => 'string',
                'description' => 'Prefix for patient numbers (e.g., PAT, HOS, MED)',
                'group' => 'patient_numbering',
            ],
            [
                'key' => 'patient_number_year_format',
                'value' => 'YYYY',
                'type' => 'string',
                'description' => 'Year format: YYYY (2025) or YY (25)',
                'group' => 'patient_numbering',
            ],
            [
                'key' => 'patient_number_separator',
                'value' => '',
                'type' => 'string',
                'description' => 'Separator between parts (empty, dash, slash)',
                'group' => 'patient_numbering',
            ],
            [
                'key' => 'patient_number_padding',
                'value' => '6',
                'type' => 'integer',
                'description' => 'Number of digits for padding (e.g., 6 = 000001)',
                'group' => 'patient_numbering',
            ],
            [
                'key' => 'patient_number_reset',
                'value' => 'never',
                'type' => 'string',
                'description' => 'When to reset counter: never, yearly, monthly',
                'group' => 'patient_numbering',
            ],
        ];

        foreach ($patientNumberConfigs as $config) {
            SystemConfiguration::updateOrCreate(
                ['key' => $config['key']],
                $config
            );
        }
    }
}
