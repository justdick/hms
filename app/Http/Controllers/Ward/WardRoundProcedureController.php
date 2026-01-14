<?php

namespace App\Http\Controllers\Ward;

use App\Events\WardRoundProcedurePerformed;
use App\Http\Controllers\Controller;
use App\Models\PatientAdmission;
use App\Models\WardRound;
use App\Models\WardRoundProcedure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WardRoundProcedureController extends Controller
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

    public function store(Request $request, PatientAdmission $admission, WardRound $wardRound): RedirectResponse
    {
        $this->authorize('update', $wardRound);

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

        $procedure = WardRoundProcedure::create([
            'ward_round_id' => $wardRound->id,
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
        event(new WardRoundProcedurePerformed($procedure));

        return redirect()
            ->back()
            ->with('success', 'Procedure documented successfully.');
    }

    public function destroy(PatientAdmission $admission, WardRound $wardRound, WardRoundProcedure $procedure): RedirectResponse
    {
        $this->authorize('update', $wardRound);

        // Ensure procedure belongs to this ward round
        if ($procedure->ward_round_id !== $wardRound->id) {
            abort(404);
        }

        $procedure->delete();

        return redirect()
            ->back()
            ->with('success', 'Procedure removed successfully.');
    }
}
