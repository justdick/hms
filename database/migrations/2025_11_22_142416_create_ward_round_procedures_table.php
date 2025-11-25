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
        Schema::create('ward_round_procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_round_id')->constrained('ward_rounds')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('users');
            $table->foreignId('minor_procedure_type_id')->constrained('minor_procedure_types')->restrictOnDelete();
            $table->text('comments')->nullable();
            $table->timestamp('performed_at');
            $table->timestamps();

            $table->index(['ward_round_id', 'performed_at']);
            $table->index('doctor_id');
            $table->index('minor_procedure_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ward_round_procedures');
    }
};
