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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('patient_number')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->enum('gender', ['male', 'female']);
            $table->date('date_of_birth');
            $table->string('phone_number')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('national_id')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');

            // Patient-level Medical History (relatively static)
            $table->text('past_medical_surgical_history')->nullable();
            $table->text('drug_history')->nullable();
            $table->text('family_history')->nullable();
            $table->text('social_history')->nullable();

            $table->timestamps();

            $table->index('first_name');
            $table->index('last_name');
            $table->index('phone_number');
            $table->index('patient_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
