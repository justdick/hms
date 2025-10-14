<?php

namespace Database\Seeders;

use App\Models\Diagnosis;
use Illuminate\Database\Seeder;

class DiagnosisSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $diagnoses = [
            ['diagnosis' => 'ACUTE RENAL FAILURE WITH DIALYSIS', 'code' => 'DG3', 'g_drg' => 'ACUTE RENAL FAILURE WITH DIALYSIS', 'icd_10' => 'N17'],
            ['diagnosis' => 'ALCOHOLISM', 'code' => 'DG4', 'g_drg' => 'ALCOHOLISM', 'icd_10' => 'F10'],
            ['diagnosis' => 'IRON DEFICIENCY ANAEMIA', 'code' => 'DG5', 'g_drg' => 'ANAEMIA', 'icd_10' => 'D50'],
            ['diagnosis' => 'IRON DEFICIENCY', 'code' => 'DG7', 'g_drg' => 'ANAEMIA', 'icd_10' => 'D50.9'],
            ['diagnosis' => 'SNAKE BITE', 'code' => 'DG9', 'g_drg' => 'ANIMAL BITES', 'icd_10' => 'T63.0'],
            ['diagnosis' => 'DOG BITE', 'code' => 'DG10', 'g_drg' => 'ANIMAL BITES', 'icd_10' => 'W54'],
            ['diagnosis' => 'RAT BITE', 'code' => 'DG11', 'g_drg' => 'ANIMAL BITES', 'icd_10' => 'W53'],
        ];

        foreach ($diagnoses as $diagnosis) {
            Diagnosis::create($diagnosis);
        }
    }
}
