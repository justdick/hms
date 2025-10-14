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
        Schema::create('ward_billing_templates', function (Blueprint $table) {
            $table->id();
            $table->string('service_name');
            $table->string('service_code')->unique();
            $table->text('description')->nullable();
            $table->enum('billing_type', ['one_time', 'daily', 'hourly', 'percentage', 'quantity_based', 'event_triggered']);
            $table->decimal('base_amount', 10, 2);
            $table->decimal('percentage_rate', 5, 2)->nullable(); // for percentage-based billing
            $table->json('calculation_rules')->nullable(); // JSON rules for complex calculations
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->json('applicable_ward_types')->nullable(); // JSON array of ward types
            $table->json('patient_category_rules')->nullable(); // JSON rules for different patient categories
            $table->json('auto_trigger_conditions')->nullable(); // JSON conditions for auto-triggering
            $table->enum('payment_requirement', ['immediate', 'deferred', 'plan']);
            $table->json('integration_points')->nullable(); // Which modules can trigger this
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['billing_type', 'is_active']);
            $table->index(['effective_from', 'effective_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ward_billing_templates');
    }
};
