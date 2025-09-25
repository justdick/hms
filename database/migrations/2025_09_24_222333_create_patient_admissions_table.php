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
        Schema::create('patient_admissions', function (Blueprint $table) {
            $table->id();
            $table->string('admission_number')->unique();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->onDelete('set null');
            $table->foreignId('bed_id')->nullable()->constrained('beds')->onDelete('set null');
            $table->foreignId('ward_id')->constrained('wards')->onDelete('cascade');
            $table->foreignId('attending_doctor_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['admitted', 'discharged', 'transferred', 'deceased']);
            $table->text('admission_reason');
            $table->text('admission_notes')->nullable();
            $table->date('expected_discharge_date')->nullable();
            $table->datetime('admitted_at');
            $table->datetime('discharged_at')->nullable();
            $table->text('discharge_notes')->nullable();
            $table->foreignId('discharged_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_admissions');
    }
};
