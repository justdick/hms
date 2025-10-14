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
        Schema::create('nursing_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_admission_id')->constrained()->onDelete('cascade');
            $table->foreignId('nurse_id')->constrained('users');
            $table->enum('type', ['assessment', 'care', 'observation', 'incident', 'handover']);
            $table->text('note');
            $table->dateTime('noted_at');
            $table->timestamps();

            $table->index(['patient_admission_id', 'noted_at'], 'nursing_notes_admission_noted_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nursing_notes');
    }
};
