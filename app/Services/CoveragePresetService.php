<?php

namespace App\Services;

class CoveragePresetService
{
    public function getPresets(): array
    {
        return [
            [
                'id' => 'nhis_standard',
                'name' => 'NHIS Standard',
                'description' => 'Standard National Health Insurance coverage',
                'coverages' => [
                    'consultation' => 70,
                    'drug' => 80,
                    'lab' => 90,
                    'procedure' => 75,
                    'ward' => 100,
                    'nursing' => 80,
                ],
            ],
            [
                'id' => 'corporate_premium',
                'name' => 'Corporate Premium',
                'description' => 'High coverage for corporate clients',
                'coverages' => [
                    'consultation' => 90,
                    'drug' => 90,
                    'lab' => 100,
                    'procedure' => 90,
                    'ward' => 100,
                    'nursing' => 90,
                ],
            ],
            [
                'id' => 'basic',
                'name' => 'Basic Coverage',
                'description' => 'Minimal coverage plan',
                'coverages' => [
                    'consultation' => 50,
                    'drug' => 60,
                    'lab' => 70,
                    'procedure' => 50,
                    'ward' => 80,
                    'nursing' => 60,
                ],
            ],
            [
                'id' => 'custom',
                'name' => 'Custom',
                'description' => 'Configure your own coverage percentages',
                'coverages' => null,
            ],
        ];
    }
}
