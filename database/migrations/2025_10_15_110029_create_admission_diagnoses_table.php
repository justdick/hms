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
        Schema::create('admission_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_admission_id')->constrained()->onDelete('cascade');
            $table->string('icd_code'); // ICD-10 or ICD-11 code
            $table->string('icd_version', 10); // '10' or '11'
            $table->text('diagnosis_name');
            $table->enum('diagnosis_type', [
                'admission', // Initial admission diagnosis
                'working', // Current active diagnosis
                'complication', // Complication that developed
                'comorbidity', // Pre-existing condition discovered
                'discharge', // Final discharge diagnosis
            ])->default('working');
            $table->morphs('source'); // consultation_id, ward_round_id, etc.
            $table->foreignId('diagnosed_by')->constrained('users');
            $table->timestamp('diagnosed_at');
            $table->boolean('is_active')->default(true);
            $table->text('clinical_notes')->nullable();
            $table->timestamps();

            $table->index(['patient_admission_id', 'is_active']);
            $table->index('diagnosed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_diagnoses');
    }
};
