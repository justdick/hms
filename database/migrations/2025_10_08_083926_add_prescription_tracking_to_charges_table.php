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
        Schema::table('charges', function (Blueprint $table) {
            // Link charges to prescriptions for pharmacy billing
            $table->foreignId('prescription_id')->nullable()->after('patient_checkin_id')->constrained('prescriptions')->cascadeOnDelete();

            // Add index for faster prescription lookups
            $table->index('prescription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->dropForeign(['prescription_id']);
            $table->dropIndex(['prescription_id']);
            $table->dropColumn('prescription_id');
        });
    }
};
