<?php

namespace App\Http\Controllers\Consultation;

use App\Events\ConsultationProcedurePerformed;
use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\ConsultationProcedure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConsultationProcedureController extends Controller
{
    /**
     * C-Section procedure subtypes.
     */
    private const CSECTION_SUBTYPES = [
        'Elective C/S',
        'Elective C/S + Sterilization',
        'Elective C/S + Hysterectomy',
        'Emergency C/S',
        'Emergency C/S + Sterilization',
        'Emergency C/S + Hysterectomy',
    ];

    public function store(Request $request, Consultation $consultation): RedirectResponse
    {
        $this->authorize('update', $consultation);

        $validated = $request->validate([
            'minor_procedure_type_id' => 'required|exists:minor_procedure_types,id',
            'indication' => 'nullable|string',
            'assistant' => 'nullable|string|max:255',
            'anaesthetist' => 'nullable|string|max:255',
            'anaesthesia_type' => 'nullable|in:spinal,local,general,regional,sedation',
            'estimated_gestational_age' => 'nullable|string|max:50',
            'parity' => 'nullable|string|max:50',
            'procedure_subtype' => ['nullable', 'string', 'max:100', 'in:'.implode(',', self::CSECTION_SUBTYPES)],
            'procedure_steps' => 'nullable|string',
            'template_selections' => 'nullable|array',
            'findings' => 'nullable|string',
            'plan' => 'nullable|string',
            'comments' => 'nullable|string',
            'performed_at' => 'required|date',
        ]);

        $procedure = ConsultationProcedure::create([
            'consultation_id' => $consultation->id,
            'doctor_id' => $request->user()->id,
            'minor_procedure_type_id' => $validated['minor_procedure_type_id'],
            'indication' => $validated['indication'] ?? null,
            'assistant' => $validated['assistant'] ?? null,
            'anaesthetist' => $validated['anaesthetist'] ?? null,
            'anaesthesia_type' => $validated['anaesthesia_type'] ?? null,
            'estimated_gestational_age' => $validated['estimated_gestational_age'] ?? null,
            'parity' => $validated['parity'] ?? null,
            'procedure_subtype' => $validated['procedure_subtype'] ?? null,
            'procedure_steps' => $validated['procedure_steps'] ?? null,
            'template_selections' => $validated['template_selections'] ?? null,
            'findings' => $validated['findings'] ?? null,
            'plan' => $validated['plan'] ?? null,
            'comments' => $validated['comments'] ?? null,
            'performed_at' => $validated['performed_at'],
        ]);

        // Dispatch event for automatic billing
        event(new ConsultationProcedurePerformed($procedure));

        return redirect()
            ->back()
            ->with('success', 'Procedure documented successfully.');
    }

    public function destroy(Consultation $consultation, ConsultationProcedure $procedure): RedirectResponse
    {
        $this->authorize('update', $consultation);

        // Ensure procedure belongs to this consultation
        if ($procedure->consultation_id !== $consultation->id) {
            abort(404);
        }

        $procedure->delete();

        return redirect()
            ->back()
            ->with('success', 'Procedure removed successfully.');
    }
}
