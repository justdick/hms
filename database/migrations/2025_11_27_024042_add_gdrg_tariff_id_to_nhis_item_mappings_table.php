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
        Schema::table('nhis_item_mappings', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['nhis_tariff_id']);

            // Make nhis_tariff_id nullable
            $table->unsignedBigInteger('nhis_tariff_id')->nullable()->change();

            // Add gdrg_tariff_id column (nullable)
            $table->foreignId('gdrg_tariff_id')->nullable()->after('nhis_tariff_id')->constrained('gdrg_tariffs');

            // Re-add foreign key for nhis_tariff_id
            $table->foreign('nhis_tariff_id')->references('id')->on('nhis_tariffs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nhis_item_mappings', function (Blueprint $table) {
            $table->dropForeign(['gdrg_tariff_id']);
            $table->dropColumn('gdrg_tariff_id');

            $table->dropForeign(['nhis_tariff_id']);
            $table->unsignedBigInteger('nhis_tariff_id')->nullable(false)->change();
            $table->foreign('nhis_tariff_id')->references('id')->on('nhis_tariffs');
        });
    }
};
