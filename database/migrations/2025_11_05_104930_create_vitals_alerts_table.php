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
        Schema::create('vitals_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vitals_schedule_id')->constrained('vitals_schedules')->cascadeOnDelete();
            $table->foreignId('patient_admission_id')->constrained('patient_admissions')->cascadeOnDelete();
            $table->timestamp('due_at');
            $table->enum('status', ['pending', 'due', 'overdue', 'completed', 'dismissed'])->default('pending');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['status', 'due_at'], 'idx_status_due');
            $table->index(['patient_admission_id', 'status'], 'idx_admission_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vitals_alerts');
    }
};
