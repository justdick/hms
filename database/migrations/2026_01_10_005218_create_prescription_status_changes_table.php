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
        // Create the history table for tracking all discontinue/resume actions
        if (!Schema::hasTable('prescription_status_changes')) {
            Schema::create('prescription_status_changes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
                $table->enum('action', ['discontinued', 'resumed']);
                $table->foreignId('performed_by_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('performed_at');
                $table->text('reason')->nullable();
                $table->timestamps();

                $table->index(['prescription_id', 'performed_at']);
            });
        }

        // Remove legacy fields from prescriptions table (history table is now source of truth)
        // Only if columns still exist
        if (Schema::hasColumn('prescriptions', 'discontinued_at')) {
            Schema::table('prescriptions', function (Blueprint $table) {
                $table->dropForeign(['discontinued_by_id']);
                $table->dropColumn(['discontinued_at', 'discontinued_by_id', 'discontinuation_reason']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore legacy fields
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->timestamp('discontinued_at')->nullable();
            $table->foreignId('discontinued_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('discontinuation_reason')->nullable();
        });

        Schema::dropIfExists('prescription_status_changes');
    }
};
