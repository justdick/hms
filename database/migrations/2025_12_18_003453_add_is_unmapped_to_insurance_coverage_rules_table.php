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
        Schema::table('insurance_coverage_rules', function (Blueprint $table) {
            $table->boolean('is_unmapped')->default(false)->after('patient_copay_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('insurance_coverage_rules', function (Blueprint $table) {
            $table->dropColumn('is_unmapped');
        });
    }
};
