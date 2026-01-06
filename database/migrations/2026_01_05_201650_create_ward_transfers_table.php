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
        Schema::create('ward_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_ward_id')->constrained('wards');
            $table->foreignId('from_bed_id')->nullable()->constrained('beds');
            $table->foreignId('to_ward_id')->constrained('wards');
            $table->text('transfer_reason');
            $table->text('transfer_notes')->nullable();
            $table->foreignId('transferred_by_id')->constrained('users');
            $table->timestamp('transferred_at');
            $table->timestamps();

            $table->index(['patient_admission_id', 'transferred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ward_transfers');
    }
};
