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
        Schema::create('bill_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charge_id')->constrained('charges')->cascadeOnDelete();
            $table->enum('adjustment_type', ['waiver', 'discount_percentage', 'discount_fixed']);
            $table->decimal('original_amount', 10, 2);
            $table->decimal('adjustment_amount', 10, 2);
            $table->decimal('final_amount', 10, 2);
            $table->text('reason');
            $table->foreignId('adjusted_by')->constrained('users');
            $table->timestamp('adjusted_at');
            $table->timestamps();

            // Indexes for performance
            $table->index('charge_id');
            $table->index('adjusted_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_adjustments');
    }
};
