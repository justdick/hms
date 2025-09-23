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
        Schema::create('patient_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('department_id')->constrained()->onDelete('cascade');
            $table->foreignId('checked_in_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('checked_in_at');
            $table->timestamp('vitals_taken_at')->nullable();
            $table->timestamp('consultation_started_at')->nullable();
            $table->timestamp('consultation_completed_at')->nullable();
            $table->enum('status', ['checked_in', 'vitals_taken', 'awaiting_consultation', 'in_consultation', 'completed', 'cancelled'])->default('checked_in');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'checked_in_at']);
            $table->index(['department_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_checkins');
    }
};
