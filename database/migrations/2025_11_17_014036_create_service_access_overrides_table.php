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
        Schema::create('service_access_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_checkin_id')->constrained('patient_checkins')->cascadeOnDelete();
            $table->string('service_type', 50);
            $table->string('service_code', 50)->nullable();
            $table->text('reason');
            $table->foreignId('authorized_by')->constrained('users');
            $table->timestamp('authorized_at');
            $table->timestamp('expires_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes for performance
            $table->index(['patient_checkin_id', 'service_type'], 'idx_checkin_service');
            $table->index(['expires_at', 'is_active'], 'idx_expires_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_access_overrides');
    }
};
