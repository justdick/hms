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
        Schema::create('insurance_tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_plan_id')->constrained('insurance_plans');
            $table->enum('item_type', ['drug', 'service', 'lab', 'procedure', 'ward']);
            $table->string('item_code');
            $table->string('item_description')->nullable();
            $table->decimal('standard_price', 10, 2);
            $table->decimal('insurance_tariff', 10, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['insurance_plan_id', 'item_type', 'item_code', 'effective_from'], 'unique_tariff');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_tariffs');
    }
};
