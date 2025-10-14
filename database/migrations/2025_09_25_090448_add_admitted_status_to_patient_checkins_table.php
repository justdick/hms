<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_checkins', function (Blueprint $table) {
            // Modify the enum to include 'admitted'
            $table->enum('status', [
                'checked_in',
                'vitals_taken',
                'awaiting_consultation',
                'in_consultation',
                'completed',
                'admitted',  // New status for admitted patients
                'cancelled',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('patient_checkins', function (Blueprint $table) {
            // Revert back to original enum values
            $table->enum('status', [
                'checked_in',
                'vitals_taken',
                'awaiting_consultation',
                'in_consultation',
                'completed',
                'cancelled',
            ])->change();
        });
    }
};
