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
        Schema::table('insurance_claim_items', function (Blueprint $table) {
            $table->foreignId('nhis_tariff_id')->nullable()->after('notes')->constrained('nhis_tariffs')->nullOnDelete();
            $table->string('nhis_code', 50)->nullable()->after('nhis_tariff_id');
            $table->decimal('nhis_price', 10, 2)->nullable()->after('nhis_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('insurance_claim_items', function (Blueprint $table) {
            $table->dropForeign(['nhis_tariff_id']);
            $table->dropColumn(['nhis_tariff_id', 'nhis_code', 'nhis_price']);
        });
    }
};
