<?php

namespace App\Listeners;

use App\Events\MinorProcedurePerformed;
use App\Models\BillingConfiguration;
use App\Models\Charge;
use Illuminate\Support\Facades\Auth;

class CreateMinorProcedureCharge
{
    /**
     * Handle the event.
     */
    public function handle(MinorProcedurePerformed $event): void
    {
        $procedure = $event->minorProcedure;

        // Check if auto billing is enabled
        if (! BillingConfiguration::getValue('auto_billing_enabled', true)) {
            return;
        }

        // Load procedure type and checkin relationships
        $procedure->load(['procedureType', 'patientCheckin.department']);

        // Check if procedure type exists
        if (! $procedure->procedureType) {
            return;
        }

        // Get procedure fee from procedure type
        $procedureFee = $procedure->procedureType->price;

        // Only create procedure charge if procedure has a specific price set
        if ($procedureFee <= 0) {
            return; // No procedure-specific charge
        }

        // Create charge for the procedure
        Charge::create([
            'patient_checkin_id' => $procedure->patient_checkin_id,
            'service_type' => 'minor_procedure',
            'service_code' => $procedure->procedureType->code,
            'description' => "Minor Procedure: {$procedure->procedureType->name}",
            'amount' => $procedureFee,
            'charge_type' => 'minor_procedure',
            'charged_at' => now(),
            'metadata' => [
                'minor_procedure_id' => $procedure->id,
                'minor_procedure_type_id' => $procedure->minor_procedure_type_id,
                'procedure_type_code' => $procedure->procedureType->code,
                'procedure_type_name' => $procedure->procedureType->name,
            ],
            'created_by_type' => Auth::user()?->getTable() ?? 'system',
            'created_by_id' => Auth::id() ?? 0,
        ]);
    }
}
