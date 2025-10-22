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
            // Add bottle_size field to store the volume in ml for bottles/vials
            // For tablets/capsules this will be null
            // For bottles: 50, 100, 150, 200, etc (ml)
            // For vials: 5, 10, 20, etc (ml)
            $table->integer('bottle_size')->nullable()->after('unit_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            $table->dropColumn('bottle_size');
        });
    }
};
