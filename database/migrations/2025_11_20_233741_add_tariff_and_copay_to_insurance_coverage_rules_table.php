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
            $table->decimal('tariff_amount', 10, 2)->nullable()->after('coverage_value')
                ->comment('Insurance negotiated price (overrides standard price if set)');
            $table->decimal('patient_copay_amount', 10, 2)->default(0)->after('patient_copay_percentage')
                ->comment('Fixed amount patient pays in addition to percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('insurance_coverage_rules', function (Blueprint $table) {
            $table->dropColumn(['tariff_amount', 'patient_copay_amount']);
        });
    }
};
