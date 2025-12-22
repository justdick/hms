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
        Schema::table('drugs', function (Blueprint $table) {
            // For drugs like Arthemeter and Pessary, NHIS requires claim quantity = 1
            // regardless of actual dispensed quantity (counted as 1 pack)
            $table->boolean('nhis_claim_qty_as_one')->default(false)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            $table->dropColumn('nhis_claim_qty_as_one');
        });
    }
};
