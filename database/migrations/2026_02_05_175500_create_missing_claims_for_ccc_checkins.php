<?php

use App\Models\InsuranceClaim;
use App\Models\PatientCheckin;
use App\Services\InsuranceClaimService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Create missing insurance claims for check-ins that have:
     * - Valid claim_check_code (from NHIA portal)
     * - Active patient insurance
     * - Completed consultation
     * - But no insurance claim record
     * 
     * This fixes a bug where claims should have been created at check-in but weren't.
     */
    public function up(): void
    {
        // Find check-ins with CCC but no claims
        $missingClaims = DB::table('patient_checkins as pc')
            ->join('consultations as c', 'c.patient_checkin_id', '=', 'pc.id')
            ->join('patient_admissions as pa', 'pa.consultation_id', '=', 'c.id')
            ->join('patients as p', 'pa.patient_id', '=', 'p.id')
            ->join('patient_insurance as pi', function ($join) {
                $join->on('pi.patient_id', '=', 'p.id')
                    ->where('pi.status', '=', 'active');
            })
            ->leftJoin('insurance_claims as ic', 'ic.patient_checkin_id', '=', 'pc.id')
            ->where('pa.migrated_from_mittag', 0)
            ->whereNotNull('pc.claim_check_code')
            ->where('pc.claim_check_code', '!=', '')
            ->whereNull('ic.id')
            ->select([
                'pc.id as checkin_id',
                'pc.claim_check_code',
                'p.id as patient_id',
                'pi.id as patient_insurance_id',
                'c.completed_at as date_of_attendance',
            ])
            ->distinct()
            ->get();

        $claimService = app(InsuranceClaimService::class);
        $created = 0;

        foreach ($missingClaims as $missing) {
            try {
                // Create the insurance claim
                $claim = $claimService->createClaim(
                    claimCheckCode: $missing->claim_check_code,
                    patientId: $missing->patient_id,
                    patientInsuranceId: $missing->patient_insurance_id,
                    patientCheckinId: $missing->checkin_id,
                    typeOfService: 'inpatient', // These are admission-related
                    dateOfAttendance: $missing->date_of_attendance ? \Carbon\Carbon::parse($missing->date_of_attendance) : null
                );

                // Link any existing charges to this claim
                $charges = \App\Models\Charge::where('checkin_id', $missing->checkin_id)->pluck('id')->toArray();
                if (!empty($charges)) {
                    $claimService->addChargesToClaim($claim, $charges);
                }

                $created++;
                \Log::info("Created missing claim {$claim->id} for CCC {$missing->claim_check_code}");
            } catch (\Exception $e) {
                \Log::error("Failed to create claim for CCC {$missing->claim_check_code}: " . $e->getMessage());
            }
        }

        \Log::info("Created {$created} missing insurance claims for check-ins with CCC");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot safely reverse - claims may have been modified
    }
};
