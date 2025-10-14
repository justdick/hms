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
            $table->text('progress_note');
            $table->enum('patient_status', [
                'improving',
                'stable',
                'deteriorating',
                'discharge_ready',
            ]);
            $table->text('clinical_impression')->nullable();
            $table->text('plan')->nullable();
            $table->dateTime('round_datetime');
            $table->timestamps();

            $table->index(['patient_admission_id', 'round_datetime'], 'ward_rounds_admission_datetime_idx');
            $table->index('doctor_id', 'ward_rounds_doctor_idx');
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
