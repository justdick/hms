<?php

namespace App\Listeners;

use App\Events\ConsultationProcedurePerformed;
use App\Models\Charge;

class CreateConsultationProcedureCharge
{
    public function handle(ConsultationProcedurePerformed $event): void
    {
        $procedure = $event->procedure;
        $procedureType = $procedure->procedureType;

        // Only create charge if procedure has a price
        if ($procedureType->price <= 0) {
            return;
        }

        Charge::create([
            'patient_checkin_id' => $procedure->consultation->patient_checkin_id,
            'service_type' => 'procedure',
            'service_code' => $procedureType->code,
            'description' => $procedureType->name.' - '.$procedureType->type,
            'amount' => $procedureType->price,
            'charge_type' => $procedureType->type === 'major' ? 'procedure' : 'minor_procedure',
            'status' => 'pending',
            'charged_at' => now(),
            'created_by_type' => 'doctor',
            'created_by_id' => $procedure->doctor_id,
        ]);
    }
}
