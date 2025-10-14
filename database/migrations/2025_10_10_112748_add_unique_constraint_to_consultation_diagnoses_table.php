<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, delete any invalid entries (diagnosis_id = 0 or null)
        DB::table('consultation_diagnoses')
            ->where('diagnosis_id', '<=', 0)
            ->orWhereNull('diagnosis_id')
            ->delete();

        // Then, remove duplicates, keeping only the oldest entry for each combination
        $duplicates = DB::table('consultation_diagnoses')
            ->select('consultation_id', 'diagnosis_id', 'type', DB::raw('MIN(id) as keep_id'))
            ->groupBy('consultation_id', 'diagnosis_id', 'type')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('consultation_diagnoses')
                ->where('consultation_id', $duplicate->consultation_id)
                ->where('diagnosis_id', $duplicate->diagnosis_id)
                ->where('type', $duplicate->type)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('consultation_diagnoses', function (Blueprint $table) {
            // Add unique constraint to prevent duplicate diagnoses
            $table->unique(['consultation_id', 'diagnosis_id', 'type'], 'unique_consultation_diagnosis');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consultation_diagnoses', function (Blueprint $table) {
            $table->dropUnique('unique_consultation_diagnosis');
        });
    }
};
