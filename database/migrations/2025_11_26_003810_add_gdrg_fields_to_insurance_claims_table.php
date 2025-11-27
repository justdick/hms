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
        Schema::table('insurance_claims', function (Blueprint $table) {
            $table->foreignId('gdrg_tariff_id')->nullable()->after('c_drg_code')->constrained('gdrg_tariffs')->nullOnDelete();
            $table->decimal('gdrg_amount', 10, 2)->nullable()->after('gdrg_tariff_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('insurance_claims', function (Blueprint $table) {
            $table->dropForeign(['gdrg_tariff_id']);
            $table->dropColumn(['gdrg_tariff_id', 'gdrg_amount']);
        });
    }
};
