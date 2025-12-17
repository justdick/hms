<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Service date allows backdating entries made during power outages.
     * - service_date: The actual date the service occurred (date only, no time)
     * - created_at: When the record was entered into the system
     */
    public function up(): void
    {
        Schema::table('patient_checkins', function (Blueprint $table) {
            $table->date('service_date')->nullable()->after('checked_in_at');
        });

        Schema::table('consultations', function (Blueprint $table) {
            $table->date('service_date')->nullable()->after('started_at');
        });

        // Backfill existing records with date from checked_in_at/started_at
        DB::statement('UPDATE patient_checkins SET service_date = DATE(checked_in_at) WHERE service_date IS NULL');
        DB::statement('UPDATE consultations SET service_date = DATE(started_at) WHERE service_date IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_checkins', function (Blueprint $table) {
            $table->dropColumn('service_date');
        });

        Schema::table('consultations', function (Blueprint $table) {
            $table->dropColumn('service_date');
        });
    }
};
