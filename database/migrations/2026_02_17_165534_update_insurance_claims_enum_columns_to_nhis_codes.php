<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Update type_of_attendance and type_of_service enums to use NHIS codes.
     */
    public function up(): void
    {
        // Step 1: Expand enums to include both old and new values
        DB::statement("ALTER TABLE insurance_claims MODIFY COLUMN type_of_attendance ENUM('emergency','acute','routine','EAE','ANC','PNC','FP','CWC','REV') NOT NULL DEFAULT 'routine'");
        DB::statement("ALTER TABLE insurance_claims MODIFY COLUMN type_of_service ENUM('inpatient','outpatient','OPD','IPD') NOT NULL DEFAULT 'outpatient'");

        // Step 2: Map existing data to NHIS codes
        DB::table('insurance_claims')
            ->whereIn('type_of_attendance', ['emergency', 'acute', 'routine'])
            ->update(['type_of_attendance' => 'EAE']);

        DB::table('insurance_claims')
            ->where('type_of_service', 'outpatient')
            ->update(['type_of_service' => 'OPD']);

        DB::table('insurance_claims')
            ->where('type_of_service', 'inpatient')
            ->update(['type_of_service' => 'IPD']);

        // Step 3: Shrink enums to only NHIS codes
        DB::statement("ALTER TABLE insurance_claims MODIFY COLUMN type_of_attendance ENUM('EAE','ANC','PNC','FP','CWC','REV') NOT NULL DEFAULT 'EAE'");
        DB::statement("ALTER TABLE insurance_claims MODIFY COLUMN type_of_service ENUM('OPD','IPD') NOT NULL DEFAULT 'OPD'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Expand enums to include both old and new values
        DB::statement("ALTER TABLE insurance_claims MODIFY COLUMN type_of_attendance ENUM('emergency','acute','routine','EAE','ANC','PNC','FP','CWC','REV') NOT NULL DEFAULT 'EAE'");
        DB::statement("ALTER TABLE insurance_claims MODIFY COLUMN type_of_service ENUM('inpatient','outpatient','OPD','IPD') NOT NULL DEFAULT 'OPD'");

        // Step 2: Map NHIS codes back to old values
        DB::table('insurance_claims')
            ->whereIn('type_of_attendance', ['EAE', 'ANC', 'PNC', 'FP', 'CWC', 'REV'])
            ->update(['type_of_attendance' => 'routine']);

        DB::table('insurance_claims')
            ->where('type_of_service', 'OPD')
            ->update(['type_of_service' => 'outpatient']);

        DB::table('insurance_claims')
            ->where('type_of_service', 'IPD')
            ->update(['type_of_service' => 'inpatient']);

        // Step 3: Shrink enums to only old values
        DB::statement("ALTER TABLE insurance_claims MODIFY COLUMN type_of_attendance ENUM('emergency','acute','routine') NOT NULL DEFAULT 'routine'");
        DB::statement("ALTER TABLE insurance_claims MODIFY COLUMN type_of_service ENUM('inpatient','outpatient') NOT NULL");
    }
};
