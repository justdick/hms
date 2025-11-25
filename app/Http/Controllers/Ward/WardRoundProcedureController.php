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
    public function store(Request $request, PatientAdmission $admission, WardRound $wardRound): RedirectResponse
    {
        $this->authorize('update', $wardRound);

        $validated = $request->validate([
            'minor_procedure_type_id' => 'required|exists:minor_procedure_types,id',
            'comments' => 'nullable|string',
            'performed_at' => 'required|date',
        ]);

        $procedure = WardRoundProcedure::create([
            'ward_round_id' => $wardRound->id,
            'doctor_id' => $request->user()->id,
            'minor_procedure_type_id' => $validated['minor_procedure_type_id'],
            'comments' => $validated['comments'],
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
