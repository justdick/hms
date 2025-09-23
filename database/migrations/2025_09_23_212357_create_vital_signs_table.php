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
        Schema::create('vital_signs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->foreignId('patient_checkin_id')->constrained()->onDelete('cascade');
            $table->foreignId('recorded_by')->constrained('users')->onDelete('cascade');
            $table->decimal('blood_pressure_systolic', 5, 2)->nullable();
            $table->decimal('blood_pressure_diastolic', 5, 2)->nullable();
            $table->decimal('temperature', 5, 2)->nullable();
            $table->integer('pulse_rate')->nullable();
            $table->integer('respiratory_rate')->nullable();
            $table->decimal('weight', 6, 2)->nullable();
            $table->decimal('height', 5, 2)->nullable();
            $table->decimal('bmi', 5, 2)->nullable();
            $table->integer('oxygen_saturation')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['patient_id', 'recorded_at']);
            $table->index('patient_checkin_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vital_signs');
    }
};
