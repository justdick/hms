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
        Schema::table('charges', function (Blueprint $table) {
            $table->string('receipt_number')->nullable()->after('paid_at');
            $table->foreignId('processed_by')->nullable()->after('receipt_number')->constrained('users');
        });

        // Update status enum to include 'owing'
        // SQLite doesn't support ENUM modification, so we need to handle this differently
        if (DB::getDriverName() === 'sqlite') {
            // For SQLite, we'll just allow any string value
            // The application will handle validation
        } else {
            // For MySQL, modify the enum
            DB::statement("ALTER TABLE charges MODIFY COLUMN status ENUM('pending', 'paid', 'partial', 'waived', 'cancelled', 'voided', 'owing') DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropForeign(['processed_by']);
            $table->dropColumn(['receipt_number', 'processed_by']);
        });

        // Revert status enum
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE charges MODIFY COLUMN status ENUM('pending', 'paid', 'partial', 'waived', 'cancelled', 'voided') DEFAULT 'pending'");
        }
    }
};
