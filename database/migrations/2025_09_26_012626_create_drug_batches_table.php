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
        Schema::create('drug_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number');
            $table->date('expiry_date');
            $table->date('manufacture_date')->nullable();
            $table->integer('quantity_received');
            $table->integer('quantity_remaining');
            $table->decimal('cost_per_unit', 10, 2);
            $table->decimal('selling_price_per_unit', 10, 2);
            $table->date('received_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['drug_id', 'expiry_date']);
            $table->index(['expiry_date']);
            $table->unique(['drug_id', 'batch_number', 'supplier_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drug_batches');
    }
};
