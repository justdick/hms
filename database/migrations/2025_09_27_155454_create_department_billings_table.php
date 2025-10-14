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
        Schema::create('department_billings', function (Blueprint $table) {
            $table->id();
            $table->string('department_code')->unique(); // e.g., 'cardiology', 'emergency', 'general_medicine'
            $table->string('department_name');
            $table->decimal('consultation_fee', 10, 2);
            $table->decimal('equipment_fee', 10, 2)->default(0);
            $table->decimal('emergency_surcharge', 10, 2)->default(0);
            $table->boolean('payment_required_before_consultation')->default(true);
            $table->boolean('emergency_override_allowed')->default(true);
            $table->integer('payment_grace_period_minutes')->default(0);
            $table->boolean('allow_partial_payment')->default(false);
            $table->boolean('payment_plan_available')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['department_code', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_billings');
    }
};
