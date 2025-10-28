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
        Schema::create('insurance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_provider_id')->constrained('insurance_providers');
            $table->string('plan_name');
            $table->string('plan_code', 50);
            $table->enum('plan_type', ['individual', 'family', 'corporate'])->default('individual');
            $table->enum('coverage_type', ['inpatient', 'outpatient', 'comprehensive'])->default('comprehensive');
            $table->decimal('annual_limit', 12, 2)->nullable();
            $table->integer('visit_limit')->nullable();
            $table->decimal('default_copay_percentage', 5, 2)->nullable();
            $table->boolean('requires_referral')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['insurance_provider_id', 'plan_code'], 'unique_plan_code_per_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_plans');
    }
};
