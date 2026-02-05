<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Updates insurance claims to 'inpatient' for:
     * 1. Claims where check-in led to an admission (via consultation)
     * 2. Claims where check-in was created during an admission
     * 
     * Note: Only considers admissions NOT migrated from mittag (migrated_from_mittag = 0)
     */
    public function up(): void
    {
        // Update claims to 'inpatient' where the check-in led to an admission
        // Only for non-migrated admissions (excludes legacy data)
        DB::statement("
            UPDATE insurance_claims ic
            JOIN consultations c ON ic.patient_checkin_id = c.patient_checkin_id
            JOIN patient_admissions pa ON pa.consultation_id = c.id
            SET ic.type_of_service = 'inpatient'
            WHERE ic.type_of_service != 'inpatient'
            AND pa.migrated_from_mittag = 0
        ");

        // Also update claims where check-in was explicitly created during admission
        DB::table('insurance_claims')
            ->join('patient_checkins', 'insurance_claims.patient_checkin_id', '=', 'patient_checkins.id')
            ->where('patient_checkins.created_during_admission', 1)
            ->where('insurance_claims.type_of_service', '!=', 'inpatient')
            ->update(['insurance_claims.type_of_service' => 'inpatient']);

        // Count final results
        $counts = DB::select("SELECT type_of_service, COUNT(*) as count FROM insurance_claims GROUP BY type_of_service");

        \Log::info("IPD Claims Migration Complete. Final counts: " . json_encode($counts));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reliably reverse - would need to track original values
    }
};
