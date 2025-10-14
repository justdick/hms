<?php

namespace Database\Seeders;

use App\Models\Bed;
use App\Models\Ward;
use Illuminate\Database\Seeder;

class WardSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing data
        Ward::query()->delete();
        Bed::query()->delete();

        $wards = [
            [
                'name' => 'Ward A',
                'code' => 'WA',
                'description' => 'General medical ward for adult patients',
                'bed_count' => 20,
            ],
            [
                'name' => 'Ward B',
                'code' => 'WB',
                'description' => 'General medical ward for adult patients',
                'bed_count' => 18,
            ],
            [
                'name' => 'ICU-1',
                'code' => 'ICU1',
                'description' => 'Intensive Care Unit with specialized monitoring equipment',
                'bed_count' => 10,
                'bed_type' => 'icu',
            ],
            [
                'name' => 'NICU',
                'code' => 'NICU',
                'description' => 'Neonatal Intensive Care Unit for newborns',
                'bed_count' => 8,
                'bed_type' => 'icu',
            ],
            [
                'name' => 'Pediatric Wing',
                'code' => 'PED',
                'description' => 'Specialized ward for children and adolescents',
                'bed_count' => 15,
            ],
            [
                'name' => 'Maternity-1',
                'code' => 'MAT1',
                'description' => 'Labor, delivery and postpartum care',
                'bed_count' => 12,
                'bed_type' => 'private',
            ],
            [
                'name' => 'Surgery Recovery',
                'code' => 'SUR',
                'description' => 'Post-operative recovery and monitoring',
                'bed_count' => 16,
            ],
            [
                'name' => 'Emergency Obs',
                'code' => 'ER',
                'description' => 'Emergency department observation beds',
                'bed_count' => 6,
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

            // Create realistic occupancy patterns based on ward function
            $occupancyRate = match ($ward->code) {
                'ICU1', 'NICU' => 0.8, // ICUs typically higher occupancy
                'ER' => 0.3, // Emergency has lower occupancy
                'MAT1' => 0.6, // Maternity variable
                default => 0.65 // General wards
            };

            // Create beds for this ward
            for ($i = 1; $i <= $bedCount; $i++) {
                $bedNumber = str_pad($i, 2, '0', STR_PAD_LEFT);
                $isOccupied = $i <= ($bedCount * $occupancyRate);

                Bed::create([
                    'ward_id' => $ward->id,
                    'bed_number' => $bedNumber,
                    'status' => $isOccupied ? 'occupied' : 'available',
                    'type' => $bedType,
                    'is_active' => true,
                ]);
            }

            // Update available beds count based on actual occupied beds
            $occupiedCount = $ward->beds()->where('status', 'occupied')->count();
            $ward->update([
                'available_beds' => $bedCount - $occupiedCount,
            ]);
        }
    }
}
