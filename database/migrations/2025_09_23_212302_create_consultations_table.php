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
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_checkin_id')->constrained('patient_checkins')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('users');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->enum('status', ['in_progress', 'completed', 'paused'])->default('in_progress');
            $table->text('chief_complaint')->nullable();
            $table->text('subjective_notes')->nullable();
            $table->text('objective_notes')->nullable();
            $table->text('assessment_notes')->nullable();
            $table->text('plan_notes')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->timestamps();

            $table->index(['doctor_id', 'status']);
            $table->index(['started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
