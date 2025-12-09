<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mittag_orphaned_records', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type'); // checkin, consultation, prescription, etc.
            $table->unsignedBigInteger('old_id');
            $table->string('old_identifier')->nullable(); // folder_id, etc.
            $table->string('reason'); // patient_not_found, invalid_date, etc.
            $table->json('full_data'); // Complete record from old system
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'old_id']);
            $table->index('old_identifier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mittag_orphaned_records');
    }
};
