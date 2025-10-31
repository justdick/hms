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
        Schema::create('patient_insurance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('insurance_plan_id')->constrained('insurance_plans');
            $table->string('membership_id');
            $table->string('policy_number')->nullable();
            $table->string('folder_id_prefix', 50)->nullable();
            $table->boolean('is_dependent')->default(false);
            $table->string('principal_member_name')->nullable();
            $table->enum('relationship_to_principal', ['self', 'spouse', 'child', 'parent', 'other'])->nullable();
            $table->date('coverage_start_date');
            $table->date('coverage_end_date')->nullable();
            $table->enum('status', ['active', 'expired', 'suspended', 'cancelled'])->default('active');
            $table->string('card_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('membership_id', 'idx_patient_insurance_membership');
            $table->index('status', 'idx_patient_insurance_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patient_insurance');
    }
};
