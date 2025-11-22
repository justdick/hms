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
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_checkin_id')->constrained('patient_checkins');
            $table->string('service_type'); // consultation, laboratory, pharmacy, ward, procedure
            $table->string('service_code')->nullable(); // specific service identifier
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->enum('charge_type', ['consultation_fee', 'equipment_fee', 'emergency_surcharge', 'lab_test', 'medication', 'ward_bed', 'nursing_care', 'procedure', 'minor_procedure', 'other']);
            $table->enum('status', ['pending', 'paid', 'partial', 'waived', 'cancelled', 'voided'])->default('pending');
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->timestamp('charged_at');
            $table->timestamp('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable(); // additional charge details
            $table->string('created_by_type'); // staff role who created the charge
            $table->unsignedBigInteger('created_by_id'); // staff ID
            $table->boolean('is_emergency_override')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['patient_checkin_id', 'status']);
            $table->index(['service_type', 'status']);
            $table->index(['charged_at', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
