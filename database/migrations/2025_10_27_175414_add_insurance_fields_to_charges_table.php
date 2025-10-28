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
        Schema::table('charges', function (Blueprint $table) {
            $table->foreignId('insurance_claim_id')->nullable()->after('prescription_id')->constrained('insurance_claims');
            $table->foreignId('insurance_claim_item_id')->nullable()->after('insurance_claim_id')->constrained('insurance_claim_items');
            $table->boolean('is_insurance_claim')->default(false)->after('status');
            $table->decimal('insurance_tariff_amount', 10, 2)->nullable()->after('amount');
            $table->decimal('insurance_covered_amount', 10, 2)->default(0.00)->after('paid_amount');
            $table->decimal('patient_copay_amount', 10, 2)->default(0.00)->after('insurance_covered_amount');

            $table->index('insurance_claim_id', 'idx_insurance_claim');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropIndex('idx_insurance_claim');
            $table->dropForeign(['insurance_claim_id']);
            $table->dropForeign(['insurance_claim_item_id']);
            $table->dropColumn([
                'insurance_claim_id',
                'insurance_claim_item_id',
                'is_insurance_claim',
                'insurance_tariff_amount',
                'insurance_covered_amount',
                'patient_copay_amount',
            ]);
        });
    }
};
