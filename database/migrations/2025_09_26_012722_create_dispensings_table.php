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
        Schema::create('dispensings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drug_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drug_batch_id')->nullable()->constrained('drug_batches')->nullOnDelete();
            $table->foreignId('dispensed_by')->constrained('users')->cascadeOnDelete();
            $table->integer('quantity');
            $table->json('batch_info')->nullable(); // Tracks which batches were used and quantities
            $table->integer('quantity_dispensed')->nullable(); // Legacy field
            $table->decimal('unit_price', 10, 2)->nullable(); // Legacy field
            $table->decimal('total_amount', 10, 2)->nullable(); // Legacy field
            $table->text('instructions')->nullable();
            $table->timestamp('dispensed_at');
            $table->enum('status', ['dispensed', 'partially_dispensed', 'cancelled'])->default('dispensed');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['prescription_id', 'status']);
            $table->index(['dispensed_at']);
            $table->index(['dispensed_by']);
            $table->index(['patient_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispensings');
    }
};
