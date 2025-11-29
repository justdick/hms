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
        Schema::create('payment_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charge_id')->nullable()->constrained('charges');
            $table->foreignId('patient_id')->nullable()->constrained('patients');
            $table->foreignId('user_id')->constrained('users');
            $table->string('action'); // payment, void, refund, receipt_printed, statement_generated, override, credit_tag_added, credit_tag_removed
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index(['charge_id', 'action']);
            $table->index(['patient_id', 'action']);
            $table->index(['user_id', 'created_at']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_audit_logs');
    }
};
