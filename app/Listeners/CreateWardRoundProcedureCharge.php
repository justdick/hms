<?php

namespace App\Listeners;

use App\Events\WardRoundProcedurePerformed;
use App\Models\Charge;

class CreateWardRoundProcedureCharge
{
    public function handle(WardRoundProcedurePerformed $event): void
    {
        $procedure = $event->procedure;
        $procedureType = $procedure->procedureType;

        // Only create charge if procedure has a price
        if ($procedureType->price <= 0) {
            return;
        }

        // Get patient_checkin_id from the admission
        $admission = $procedure->wardRound->patientAdmission;
        $patientCheckinId = $admission->patient_checkin_id ?? null;

        // If no patient_checkin_id, we can't create a charge
        if (! $patientCheckinId) {
            return;
        }

        Charge::create([
            'patient_checkin_id' => $patientCheckinId,
            'service_type' => 'procedure',
            'service_code' => $procedureType->code,
            'description' => $procedureType->name.' - '.$procedureType->type.' (Ward Round)',
            'amount' => $procedureType->price,
            'charge_type' => $procedureType->type === 'major' ? 'procedure' : 'minor_procedure',
            'status' => 'pending',
            'charged_at' => now(),
            'created_by_type' => 'doctor',
            'created_by_id' => $procedure->doctor_id,
        ]);
    }
}
