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
        Schema::create('minor_procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_checkin_id')->constrained('patient_checkins')->cascadeOnDelete();
            $table->foreignId('nurse_id')->constrained('users');
            $table->foreignId('minor_procedure_type_id')->constrained('minor_procedure_types')->restrictOnDelete();
            $table->string('procedure_type')->nullable(); // Kept for backward compatibility
            $table->text('procedure_notes')->nullable();
            $table->timestamp('performed_at');
            $table->enum('status', ['in_progress', 'completed'])->default('in_progress');
            $table->timestamps();

            $table->index(['patient_checkin_id', 'status']);
            $table->index(['nurse_id', 'performed_at']);
            $table->index('minor_procedure_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minor_procedures');
    }
};
