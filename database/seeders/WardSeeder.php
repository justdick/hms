<?php

namespace Database\Seeders;

use App\Models\Bed;
use App\Models\Ward;
use Illuminate\Database\Seeder;

class WardSeeder extends Seeder
{
    public function run(): void
    {
        // Only clear existing data if database is empty (fresh migration)
        // This prevents data loss when re-running seeders
        if (Ward::count() > 0) {
            return;
        }

        Bed::query()->delete();

        // Wards based on Mittag hospital setup
        $wards = [
            [
                'name' => 'Male Ward',
                'code' => 'MW',
                'description' => 'General ward for adult male patients',
                'bed_count' => 10,
            ],
            [
                'name' => 'Female Ward',
                'code' => 'FW',
                'description' => 'General ward for adult female patients',
                'bed_count' => 10,
            ],
            [
                'name' => 'Paediatric Ward',
                'code' => 'PED',
                'description' => 'Ward for children and adolescents',
                'bed_count' => 10,
            ],
            [
                'name' => 'Maternity Ward',
                'code' => 'MAT',
                'description' => 'Labor, delivery and postpartum care',
                'bed_count' => 10,
                'bed_type' => 'private',
            ],
            [
                'name' => 'Emergency / Same Day Care',
                'code' => 'ER',
                'description' => 'Emergency department and same day observation',
                'bed_count' => 10,
            ],
        ];

        foreach ($wards as $wardData) {
            $bedCount = $wardData['bed_count'];
            $bedType = $wardData['bed_type'] ?? 'standard';
            unset($wardData['bed_count'], $wardData['bed_type']);

            $ward = Ward::create([
                ...$wardData,
                'is_active' => true,
                'total_beds' => $bedCount,
                'available_beds' => $bedCount,
            ]);

            // Create beds for this ward - all available initially
            for ($i = 1; $i <= $bedCount; $i++) {
                $bedNumber = str_pad($i, 2, '0', STR_PAD_LEFT);

                Bed::create([
                    'ward_id' => $ward->id,
                    'bed_number' => $bedNumber,
                    'status' => 'available',
                    'type' => $bedType,
                    'is_active' => true,
                ]);
            }
        }
    }
}
