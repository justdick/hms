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
        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained('consultations')->cascadeOnDelete();
            $table->foreignId('lab_service_id')->constrained('lab_services');
            $table->foreignId('ordered_by')->constrained('users');
            $table->timestamp('ordered_at');
            $table->enum('status', ['ordered', 'sample_collected', 'in_progress', 'completed', 'cancelled'])->default('ordered');
            $table->enum('priority', ['routine', 'urgent', 'stat'])->default('routine');
            $table->text('special_instructions')->nullable();
            $table->timestamp('sample_collected_at')->nullable();
            $table->timestamp('result_entered_at')->nullable();
            $table->json('result_values')->nullable();
            $table->text('result_notes')->nullable();
            $table->timestamps();

            $table->index(['consultation_id', 'status']);
            $table->index(['status', 'priority']);
            $table->index(['ordered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_orders');
    }
};
