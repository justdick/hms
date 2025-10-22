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
        Schema::table('prescriptions', function (Blueprint $table) {
            // Add dose_quantity field to store units per dose (e.g., 2 tablets, 10ml)
            // For tablets/capsules: number of units per dose (e.g., 2 tablets)
            // For liquids: volume per dose in ml (e.g., 10ml)
            $table->string('dose_quantity')->nullable()->after('duration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropColumn('dose_quantity');
        });
    }
};
