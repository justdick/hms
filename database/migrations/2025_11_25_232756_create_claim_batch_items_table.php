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
        Schema::create('claim_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_batch_id')->constrained('claim_batches')->onDelete('cascade');
            $table->foreignId('insurance_claim_id')->constrained('insurance_claims');
            $table->decimal('claim_amount', 10, 2);
            $table->decimal('approved_amount', 10, 2)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'paid'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->unique(['claim_batch_id', 'insurance_claim_id'], 'unique_batch_claim');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_batch_items');
    }
};
