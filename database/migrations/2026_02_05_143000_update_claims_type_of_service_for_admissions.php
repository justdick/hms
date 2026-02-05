<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Updates existing insurance claims to have the correct type_of_service
     * based on whether their check-in was created during an admission.
     */
    public function up(): void
    {
        // Update claims to 'inpatient' where the check-in was created during admission
        $updated = DB::table('insurance_claims')
            ->join('patient_checkins', 'insurance_claims.patient_checkin_id', '=', 'patient_checkins.id')
            ->where('patient_checkins.created_during_admission', 1)
            ->where('insurance_claims.type_of_service', '!=', 'inpatient')
            ->update(['insurance_claims.type_of_service' => 'inpatient']);

        \Log::info("Updated {$updated} insurance claims to type_of_service='inpatient' based on created_during_admission flag");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reliably reverse this - would need to track which claims were changed
        // Leave as-is since we can't know which claims were originally set to 'inpatient'
    }
};
