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
        Schema::create('insurance_coverage_rule_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_coverage_rule_id')
                ->constrained('insurance_coverage_rules', 'id', 'icr_history_rule_fk')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users', 'id', 'icr_history_user_fk')
                ->nullOnDelete();
            $table->string('action'); // 'created', 'updated', 'deleted'
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('notes')->nullable();
            $table->string('batch_id')->nullable(); // For grouping related changes
            $table->timestamps();

            $table->index(['insurance_coverage_rule_id', 'created_at'], 'icr_history_rule_created_idx');
            $table->index('batch_id', 'icr_history_batch_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insurance_coverage_rule_history');
    }
};
