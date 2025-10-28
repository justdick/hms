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
        Schema::create('insurance_coverage_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_plan_id')->constrained('insurance_plans');
            $table->enum('coverage_category', ['consultation', 'drug', 'lab', 'procedure', 'ward', 'nursing']);
            $table->string('item_code')->nullable();
            $table->string('item_description')->nullable();
            $table->boolean('is_covered')->default(true);
            $table->enum('coverage_type', ['percentage', 'fixed', 'full', 'excluded'])->default('percentage');
            $table->decimal('coverage_value', 10, 2)->default(100.00);
            $table->decimal('patient_copay_percentage', 5, 2)->default(0.00);
            $table->integer('max_quantity_per_visit')->nullable();
            $table->decimal('max_amount_per_visit', 10, 2)->nullable();
            $table->boolean('requires_preauthorization')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['insurance_plan_id', 'coverage_category'], 'idx_plan_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_coverage_rules');
    }
};
