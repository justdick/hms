<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lab_orders', function (Blueprint $table) {
            $table->boolean('is_unpriced')->default(false)->after('status');
        });

        // Add 'external_referral' to the status enum (MySQL only)
        // SQLite doesn't support ENUM, so we skip this for SQLite
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE lab_orders MODIFY COLUMN status ENUM('ordered', 'sample_collected', 'in_progress', 'completed', 'cancelled', 'external_referral') DEFAULT 'ordered'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'external_referral' from the status enum (MySQL only)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE lab_orders MODIFY COLUMN status ENUM('ordered', 'sample_collected', 'in_progress', 'completed', 'cancelled') DEFAULT 'ordered'");
        }

        Schema::table('lab_orders', function (Blueprint $table) {
            $table->dropColumn('is_unpriced');
        });
    }
};
