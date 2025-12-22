<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Medication Administration Record (MAR) - On-demand recording system.
     * Nurses record medication administrations as they happen, no pre-scheduling required.
     */
    public function up(): void
    {
        Schema::create('medication_administrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->onDelete('cascade');
            $table->foreignId('patient_admission_id')->constrained()->onDelete('cascade');
            $table->foreignId('administered_by_id')->nullable()->constrained('users');
            $table->dateTime('administered_at'); // When the medication was given/held/refused/omitted
            $table->enum('status', ['given', 'held', 'refused', 'omitted']);
            $table->string('dosage_given')->nullable();
            $table->string('route')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['patient_admission_id', 'administered_at'], 'med_admin_admission_at_idx');
            $table->index(['administered_by_id', 'administered_at'], 'med_admin_by_at_idx');
            $table->index(['prescription_id', 'administered_at'], 'med_admin_prescription_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medication_administrations');
    }
};
