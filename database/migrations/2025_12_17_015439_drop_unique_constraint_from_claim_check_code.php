<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * NHIS can reuse CCCs across different patients/dates, so we remove the global
     * unique constraint. Validation will instead check for same patient + same CCC
     * within 24 hours to prevent accidental duplicate check-ins.
     */
    public function up(): void
    {
        Schema::table('patient_checkins', function (Blueprint $table) {
            $table->dropUnique('patient_checkins_claim_check_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_checkins', function (Blueprint $table) {
            $table->unique('claim_check_code');
        });
    }
};
