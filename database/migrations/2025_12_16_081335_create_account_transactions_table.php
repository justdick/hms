<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_account_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_number')->unique();
            $table->enum('type', [
                'deposit',
                'charge_deduction',
                'payment',
                'refund',
                'adjustment',
                'credit_limit_change',
            ]);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->foreignId('charge_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_reference')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users');
            $table->timestamp('transacted_at');
            $table->timestamps();

            $table->index(['patient_account_id', 'type']);
            $table->index(['patient_account_id', 'transacted_at']);
            $table->index('charge_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_transactions');
    }
};
