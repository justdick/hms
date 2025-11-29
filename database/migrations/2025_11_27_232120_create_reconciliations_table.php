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
        Schema::create('reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cashier_id')->constrained('users');
            $table->foreignId('finance_officer_id')->constrained('users');
            $table->date('reconciliation_date');
            $table->decimal('system_total', 12, 2);
            $table->decimal('physical_count', 12, 2);
            $table->decimal('variance', 12, 2);
            $table->text('variance_reason')->nullable();
            $table->json('denomination_breakdown')->nullable();
            $table->enum('status', ['balanced', 'variance', 'pending'])->default('pending');
            $table->timestamps();

            $table->unique(['cashier_id', 'reconciliation_date']);
            $table->index(['reconciliation_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliations');
    }
};
