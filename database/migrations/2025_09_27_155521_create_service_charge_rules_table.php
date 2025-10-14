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
        Schema::create('service_charge_rules', function (Blueprint $table) {
            $table->id();
            $table->string('service_type'); // consultation, laboratory, pharmacy, ward, procedure
            $table->string('service_code')->nullable(); // specific service identifier
            $table->string('service_name');
            $table->enum('charge_timing', ['on_checkin', 'before_service', 'during_service', 'after_service']);
            $table->enum('payment_required', ['mandatory', 'optional', 'deferred']);
            $table->enum('payment_timing', ['immediate', 'within_24h', 'end_of_visit', 'monthly']);
            $table->boolean('emergency_override_allowed')->default(true);
            $table->boolean('partial_payment_allowed')->default(false);
            $table->boolean('payment_plans_available')->default(true);
            $table->integer('grace_period_days')->default(0);
            $table->boolean('late_fees_enabled')->default(false);
            $table->boolean('service_blocking_enabled')->default(true);
            $table->boolean('hide_details_until_paid')->default(false); // for lab tests
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['service_type', 'is_active']);
            $table->index(['service_code', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_charge_rules');
    }
};
