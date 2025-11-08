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
        Schema::create('medication_schedule_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medication_administration_id')
                ->constrained('medication_administrations')
                ->onDelete('cascade')
                ->name('med_schedule_adj_med_admin_id_fk');
            $table->foreignId('adjusted_by_id')
                ->constrained('users')
                ->name('med_schedule_adj_adjusted_by_id_fk');
            $table->dateTime('original_time');
            $table->dateTime('adjusted_time');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['medication_administration_id', 'created_at'], 'med_schedule_adj_med_admin_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medication_schedule_adjustments');
    }
};
