<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * These indexes optimize the dispensing patient search query which uses:
     * - status filtering (prescribed, reviewed, dispensed)
     * - created_at date filtering
     * - migrated_from_mittag filtering
     */
    public function up(): void
    {
        // Add composite index for prescription search - covers the most common query pattern
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->index(['status', 'migrated_from_mittag', 'created_at'], 'idx_prescriptions_dispensing_search');
        });

        // Add index on minor_procedure_supplies for created_at filtering
        Schema::table('minor_procedure_supplies', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'idx_supplies_dispensing_search');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropIndex('idx_prescriptions_dispensing_search');
        });

        Schema::table('minor_procedure_supplies', function (Blueprint $table) {
            $table->dropIndex('idx_supplies_dispensing_search');
        });
    }
};
