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
        Schema::create('pricing_change_logs', function (Blueprint $table) {
            $table->id();
            $table->string('item_type');           // 'drug', 'lab', 'consultation', 'procedure'
            $table->unsignedBigInteger('item_id');
            $table->string('item_code')->nullable();
            $table->string('field_changed');        // 'cash_price', 'copay', 'coverage', 'tariff'
            $table->foreignId('insurance_plan_id')->nullable()->constrained('insurance_plans')->nullOnDelete();
            $table->decimal('old_value', 10, 2)->nullable();
            $table->decimal('new_value', 10, 2);
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamps();

            $table->index(['item_type', 'item_id'], 'pricing_logs_item_idx');
            $table->index('insurance_plan_id', 'pricing_logs_plan_idx');
            $table->index('changed_by', 'pricing_logs_user_idx');
            $table->index('created_at', 'pricing_logs_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_change_logs');
    }
};
