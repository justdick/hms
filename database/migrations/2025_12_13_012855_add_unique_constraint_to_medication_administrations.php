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
        Schema::table('medication_administrations', function (Blueprint $table) {
            // Prevent duplicate scheduled times for the same prescription
            $table->unique(
                ['prescription_id', 'scheduled_time'],
                'medication_administrations_prescription_scheduled_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->dropUnique('medication_administrations_prescription_scheduled_unique');
        });
    }
};
