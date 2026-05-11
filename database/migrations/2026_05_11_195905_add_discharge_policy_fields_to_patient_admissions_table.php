<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds audit columns capturing context when a patient is discharged with
     * an outstanding balance (per the configurable discharge policy).
     */
    public function up(): void
    {
        Schema::table('patient_admissions', function (Blueprint $table) {
            $table->decimal('discharge_outstanding_balance', 12, 2)
                ->nullable()
                ->after('discharge_notes')
                ->comment('Outstanding balance at time of discharge (if any)');

            $table->string('discharge_ack_reason', 100)
                ->nullable()
                ->after('discharge_outstanding_balance')
                ->comment('Reason selected when discharging with outstanding balance');

            $table->text('discharge_ack_note')
                ->nullable()
                ->after('discharge_ack_reason')
                ->comment('Free text acknowledgement provided at discharge');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_admissions', function (Blueprint $table) {
            $table->dropColumn([
                'discharge_outstanding_balance',
                'discharge_ack_reason',
                'discharge_ack_note',
            ]);
        });
    }
};
