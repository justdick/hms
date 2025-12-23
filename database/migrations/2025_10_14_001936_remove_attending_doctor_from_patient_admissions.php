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
        Schema::table('patient_admissions', function (Blueprint $table) {
            // Check if foreign key exists before dropping
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'patient_admissions' 
                AND COLUMN_NAME = 'attending_doctor_id'
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            if (count($foreignKeys) > 0) {
                $table->dropForeign(['attending_doctor_id']);
            }

            if (Schema::hasColumn('patient_admissions', 'attending_doctor_id')) {
                $table->dropColumn('attending_doctor_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_admissions', function (Blueprint $table) {
            $table->foreignId('attending_doctor_id')->after('ward_id')->constrained('users')->onDelete('cascade');
        });
    }
};
