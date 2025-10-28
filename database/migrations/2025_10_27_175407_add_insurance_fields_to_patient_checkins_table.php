<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patient_checkins', function (Blueprint $table) {
            $table->string('claim_check_code', 50)->unique()->nullable()->after('status');
            $table->index('claim_check_code', 'idx_claim_check_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_checkins', function (Blueprint $table) {
            $table->dropIndex('idx_claim_check_code');
            $table->dropColumn('claim_check_code');
        });
    }
};
