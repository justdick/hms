<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Make price columns nullable to support centralized pricing management.
     * New items are created without a price (unpriced) and prices are set
     * via the Pricing Dashboard.
     */
    public function up(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->nullable()->change();
        });

        Schema::table('lab_services', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->change();
        });

        Schema::table('billing_services', function (Blueprint $table) {
            $table->decimal('base_price', 10, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: Reverting this migration may fail if there are null values
        // in the price columns. Set a default value first if needed.
        Schema::table('drugs', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->nullable(false)->change();
        });

        Schema::table('lab_services', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable(false)->change();
        });

        Schema::table('billing_services', function (Blueprint $table) {
            $table->decimal('base_price', 10, 2)->nullable(false)->change();
        });
    }
};
