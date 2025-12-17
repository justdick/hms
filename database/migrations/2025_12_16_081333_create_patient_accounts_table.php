<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('account_number')->unique();
            $table->decimal('balance', 12, 2)->default(0);
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->foreignId('credit_authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('credit_authorized_at')->nullable();
            $table->string('credit_reason')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['balance']);
            $table->index(['credit_limit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_accounts');
    }
};
