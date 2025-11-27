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
        Schema::create('insurance_claim_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_claim_id')->constrained('insurance_claims')->cascadeOnDelete();
            $table->foreignId('diagnosis_id')->constrained('diagnoses')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            // Unique constraint to prevent duplicate diagnoses on the same claim
            $table->unique(['insurance_claim_id', 'diagnosis_id'], 'unique_claim_diagnosis');

            // Index for efficient queries
            $table->index(['insurance_claim_id', 'is_primary'], 'idx_claim_diagnosis_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_claim_diagnoses');
    }
};
