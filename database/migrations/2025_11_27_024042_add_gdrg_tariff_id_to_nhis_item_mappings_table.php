<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function foreignKeyExistsOnColumn(string $table, string $column): bool
    {
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ? 
            AND COLUMN_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$table, $column]);

        return count($foreignKeys) > 0;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('nhis_item_mappings', function (Blueprint $table) {
            // Drop the foreign key constraint first (if exists)
            if ($this->foreignKeyExistsOnColumn('nhis_item_mappings', 'nhis_tariff_id')) {
                $table->dropForeign(['nhis_tariff_id']);
            }

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
