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
        Schema::create('nhis_item_mappings', function (Blueprint $table) {
            $table->id();
            $table->enum('item_type', ['drug', 'lab_service', 'procedure', 'consumable']);
            $table->unsignedBigInteger('item_id');
            $table->string('item_code', 50);
            $table->foreignId('nhis_tariff_id')->constrained('nhis_tariffs');
            $table->timestamps();

            // Unique constraint on item_type + item_id (each item can only be mapped once)
            $table->unique(['item_type', 'item_id'], 'unique_item_mapping');

            // Index for looking up by item type and code
            $table->index(['item_type', 'item_code'], 'idx_item_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nhis_item_mappings');
    }
};
