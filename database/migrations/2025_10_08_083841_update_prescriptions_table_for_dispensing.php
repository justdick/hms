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
        // First, update existing records to ensure they have a valid status
        DB::table('prescriptions')
            ->where('status', 'dispensed')
            ->update(['status' => 'prescribed']);

        Schema::table('prescriptions', function (Blueprint $table) {
            // Update status enum to include new statuses
            $table->enum('status', [
                'prescribed',           // Doctor created
                'reviewed',             // Pharmacy reviewed, billing adjusted
                'dispensed',            // Fully dispensed
                'partially_dispensed',  // Partial given
                'not_dispensed',        // External (won't dispense)
                'cancelled',            // Cancelled
            ])->default('prescribed')->change();

            // Add new columns for dispensing workflow
            $table->integer('quantity_to_dispense')->nullable()->after('quantity');
            $table->integer('quantity_dispensed')->default(0)->after('quantity_to_dispense');
            $table->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('dispensing_notes')->nullable()->after('reviewed_at');
            $table->string('external_reason')->nullable()->after('dispensing_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            // Drop new columns
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn([
                'quantity_to_dispense',
                'quantity_dispensed',
                'reviewed_by',
                'reviewed_at',
                'dispensing_notes',
                'external_reason',
            ]);

            // Revert status enum to original values
            $table->enum('status', ['prescribed', 'dispensed', 'cancelled'])->default('prescribed')->change();
        });
    }
};
