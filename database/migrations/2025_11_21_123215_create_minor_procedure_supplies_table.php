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
        Schema::create('minor_procedure_supplies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minor_procedure_id')->constrained('minor_procedures')->cascadeOnDelete();
            $table->foreignId('drug_id')->constrained('drugs');
            $table->decimal('quantity', 10, 2);

            // Status workflow: pending -> reviewed -> dispensed
            $table->string('status')->default('pending'); // pending, reviewed, dispensed, cancelled

            // Review fields
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->decimal('quantity_to_dispense', 10, 2)->nullable();
            $table->text('dispensing_notes')->nullable();

            // Dispensing fields (kept for backward compatibility)
            $table->boolean('dispensed')->default(false);
            $table->timestamp('dispensed_at')->nullable();
            $table->foreignId('dispensed_by')->nullable()->constrained('users');

            $table->timestamps();

            $table->index(['minor_procedure_id', 'status']);
            $table->index(['drug_id']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('minor_procedure_supplies');
    }
};
