<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds composite index to optimize pricing dashboard queries.
     * The existing idx_plan_category index only covers (insurance_plan_id, coverage_category),
     * but queries also filter by item_code and is_active, causing slow lookups.
     */
    public function up(): void
    {
        Schema::table('insurance_coverage_rules', function (Blueprint $table) {
            // Composite index for item-specific rule lookups
            $table->index(
                ['insurance_plan_id', 'coverage_category', 'item_code', 'is_active'],
                'idx_plan_category_item'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('insurance_coverage_rules', function (Blueprint $table) {
            $table->dropIndex('idx_plan_category_item');
        });
    }
};
