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
        Schema::create('ward_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_admission_id')->constrained()->onDelete('cascade');
            $table->foreignId('doctor_id')->constrained('users');
            $table->integer('day_number')->unsigned();
            $table->enum('round_type', ['daily_round', 'specialist_consult', 'procedure_note'])->default('daily_round');

            // Fields matching consultation structure for UI reuse
            $table->text('presenting_complaint')->nullable(); // Interval update or new concerns
            $table->text('history_presenting_complaint')->nullable(); // Progress since last review
            $table->text('on_direct_questioning')->nullable(); // Review of systems
            $table->text('examination_findings')->nullable(); // Physical examination
            $table->text('assessment_notes')->nullable(); // Clinical assessment
            $table->text('plan_notes')->nullable(); // Management plan

            $table->enum('patient_status', [
                'improving',
                'stable',
                'deteriorating',
                'discharge_ready',
                'critical',
            ])->default('stable');

            $table->timestamp('round_datetime');
            $table->timestamps();

            $table->index(['patient_admission_id', 'round_datetime'], 'ward_rounds_admission_datetime_idx');
            $table->index('doctor_id', 'ward_rounds_doctor_idx');
            $table->index('day_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ward_rounds');
    }
};
