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
        Schema::create('drugs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('generic_name')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('drug_code')->unique();
            $table->enum('category', [
                'analgesics', 'antibiotics', 'antivirals', 'antifungals',
                'cardiovascular', 'diabetes', 'respiratory', 'gastrointestinal',
                'neurological', 'psychiatric', 'dermatological', 'vaccines',
                'vitamins', 'supplements', 'other',
            ]);
            $table->enum('form', [
                'tablet', 'capsule', 'syrup', 'suspension', 'injection',
                'drops', 'cream', 'ointment', 'inhaler', 'patch', 'other',
            ]);
            $table->text('description')->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->enum('unit_type', ['piece', 'bottle', 'vial', 'tube', 'box']);
            $table->integer('minimum_stock_level')->default(10);
            $table->integer('maximum_stock_level')->default(1000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'is_active']);
            $table->index(['name']);
            $table->index(['generic_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drugs');
    }
};
