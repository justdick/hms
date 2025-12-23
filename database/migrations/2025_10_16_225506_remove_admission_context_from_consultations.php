<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function foreignKeyExistsOnColumn(string $table, string $column): bool
    {
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ? 
            AND COLUMN_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$table, $column]);

        return count($foreignKeys) > 0;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('consultations', 'admission_id')) {
            Schema::table('consultations', function (Blueprint $table) {
                if ($this->foreignKeyExistsOnColumn('consultations', 'admission_id')) {
                    $table->dropForeign(['admission_id']);
                }
                $table->dropColumn('admission_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->foreignId('admission_id')->nullable()->after('patient_checkin_id')->constrained('patient_admissions')->nullOnDelete();
            $table->index('admission_id');
        });
    }
};
