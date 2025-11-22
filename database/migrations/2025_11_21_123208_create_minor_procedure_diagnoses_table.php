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
        Schema::create('minor_procedure_diagnoses', function (Blueprint $table) {
            $table->foreignId('minor_procedure_id')->constrained('minor_procedures')->cascadeOnDelete();
            $table->foreignId('diagnosis_id')->constrained('diagnoses')->cascadeOnDelete();

            $table->primary(['minor_procedure_id', 'diagnosis_id'], 'minor_procedure_diagnoses_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minor_procedure_diagnoses');
    }
};
