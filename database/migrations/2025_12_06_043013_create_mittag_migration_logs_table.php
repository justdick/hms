<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mittag_migration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 50); // patients, drugs, checkins, etc.
            $table->unsignedBigInteger('old_id');
            $table->unsignedBigInteger('new_id')->nullable();
            $table->string('old_identifier', 100)->nullable(); // folder_id for patients
            $table->string('new_identifier', 100)->nullable(); // patient_number for patients
            $table->enum('status', ['success', 'skipped', 'failed']);
            $table->text('notes')->nullable();
            $table->json('old_data')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'old_id']);
            $table->index(['entity_type', 'old_identifier']);
            $table->index(['entity_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mittag_migration_logs');
    }
};
