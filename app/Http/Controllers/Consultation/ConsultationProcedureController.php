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
    public function store(Request $request, Consultation $consultation): RedirectResponse
    {
        $this->authorize('update', $consultation);

        $validated = $request->validate([
            'minor_procedure_type_id' => 'required|exists:minor_procedure_types,id',
            'comments' => 'nullable|string',
            'performed_at' => 'required|date',
        ]);

        $procedure = ConsultationProcedure::create([
            'consultation_id' => $consultation->id,
            'doctor_id' => $request->user()->id,
            'minor_procedure_type_id' => $validated['minor_procedure_type_id'],
            'comments' => $validated['comments'],
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
