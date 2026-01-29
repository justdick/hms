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
        Schema::create('delivery_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_admission_id')->constrained()->onDelete('cascade');
            $table->foreignId('patient_id')->constrained()->onDelete('cascade');
            $table->date('delivery_date');
            $table->string('gestational_age')->nullable(); // e.g., "40 WEEKS", "38+3"
            $table->string('parity')->nullable(); // e.g., "G4P3", "G1P0"
            $table->string('delivery_mode'); // SVD, Emergency C/S, Elective C/S, etc.

            // Outcome fields - stored as JSON for flexibility (can have multiple babies for twins)
            $table->json('outcomes')->nullable();
            /*
             * outcomes structure:
             * [
             *   {
             *     "time_of_delivery": "7:20AM",
             *     "sex": "male" | "female",
             *     "apgar_scores": "7 AND 9" or "7/10, 9/10",
             *     "birth_weight_kg": 2.9,
             *     "head_circumference_cm": 32,
             *     "full_length_cm": 43,
             *     "outcome_type": "live_birth" | "stillbirth"
             *   }
             * ]
             */

            // For C-sections - surgical notes
            $table->text('surgical_notes')->nullable();

            // General notes
            $table->text('notes')->nullable();

            $table->foreignId('recorded_by_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('last_edited_by_id')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();

            // Indexes
            $table->index('delivery_date');
            $table->index(['patient_id', 'delivery_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_records');
    }
};
