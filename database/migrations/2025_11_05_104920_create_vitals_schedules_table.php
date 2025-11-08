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
        Schema::create('vitals_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_admission_id')->constrained('patient_admissions')->cascadeOnDelete();
            $table->unsignedInteger('interval_minutes');
            $table->timestamp('next_due_at')->nullable();
            $table->timestamp('last_recorded_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['next_due_at', 'is_active'], 'idx_next_due_at');
            $table->index(['patient_admission_id', 'is_active'], 'idx_admission_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vitals_schedules');
    }
};
