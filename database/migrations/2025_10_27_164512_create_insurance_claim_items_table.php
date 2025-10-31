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
        Schema::create('insurance_claim_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_claim_id')->constrained('insurance_claims')->onDelete('cascade');
            $table->foreignId('charge_id')->nullable()->constrained('charges');
            $table->date('item_date');
            $table->enum('item_type', ['consultation', 'drug', 'lab', 'procedure', 'ward', 'nursing']);

            // Item details
            $table->string('code');
            $table->text('description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_tariff', 10, 2);
            $table->decimal('subtotal', 10, 2);

            // Coverage split
            $table->boolean('is_covered')->default(true);
            $table->decimal('coverage_percentage', 5, 2)->nullable();
            $table->decimal('insurance_pays', 10, 2)->default(0.00);
            $table->decimal('patient_pays', 10, 2)->default(0.00);

            // Vetting
            $table->boolean('is_approved')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['insurance_claim_id', 'item_type'], 'idx_claim_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_claim_items');
    }
};
