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
        Schema::create('claim_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number', 50)->unique();
            $table->string('name', 255);
            $table->date('submission_period');
            $table->enum('status', ['draft', 'finalized', 'submitted', 'processing', 'completed'])->default('draft');
            $table->integer('total_claims')->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('status');
            $table->index('submission_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('claim_batches');
    }
};
