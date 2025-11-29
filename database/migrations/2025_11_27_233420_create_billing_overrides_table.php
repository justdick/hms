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
        Schema::create('billing_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_checkin_id')->constrained('patient_checkins');
            $table->foreignId('charge_id')->nullable()->constrained('charges');
            $table->foreignId('authorized_by')->constrained('users');
            $table->string('service_type');
            $table->text('reason');
            $table->enum('status', ['active', 'used', 'expired'])->default('active');
            $table->timestamp('authorized_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['patient_checkin_id', 'status']);
            $table->index(['charge_id', 'status']);
            $table->index(['authorized_by', 'authorized_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_overrides');
    }
};
