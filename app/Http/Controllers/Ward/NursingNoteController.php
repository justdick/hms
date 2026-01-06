<?php

namespace App\Http\Controllers\Ward;

use App\Http\Controllers\Controller;
use App\Models\NursingNote;
use App\Models\PatientAdmission;
use Illuminate\Http\Request;

class NursingNoteController extends Controller
{
    public function index(PatientAdmission $admission)
    {
        $this->authorize('viewAny', NursingNote::class);

        $notes = $admission->nursingNotes()
            ->with('nurse:id,name')
            ->orderBy('noted_at', 'desc')
            ->get();

        return response()->json([
            'nursing_notes' => $notes,
        ]);
    }

    public function store(Request $request, PatientAdmission $admission)
    {
        $this->authorize('create', NursingNote::class);

        $validated = $request->validate([
            'type' => 'required|in:admission,assessment,care,observation,incident,handover',
            'note' => 'required|string|min:10',
            'noted_at' => 'nullable|date',
        ]);

        $nursingNote = $admission->nursingNotes()->create([
            'nurse_id' => auth()->id(),
            'type' => $validated['type'],
            'note' => $validated['note'],
            'noted_at' => $validated['noted_at'] ?? now(),
        ]);

        $nursingNote->load('nurse:id,name');

        return redirect()->back()->with('success', 'Nursing note added successfully.');
    }

    public function update(Request $request, PatientAdmission $admission, NursingNote $nursingNote)
    {
        $this->authorize('update', $nursingNote);

        $validated = $request->validate([
            'type' => 'required|in:admission,assessment,care,observation,incident,handover',
            'note' => 'required|string|min:10',
            'noted_at' => 'nullable|date',
        ]);

        $nursingNote->update([
            'type' => $validated['type'],
            'note' => $validated['note'],
            'noted_at' => $validated['noted_at'] ?? $nursingNote->noted_at,
        ]);

        $nursingNote->load('nurse:id,name');

        return redirect()->back()->with('success', 'Nursing note updated successfully.');
    }

    public function destroy(PatientAdmission $admission, NursingNote $nursingNote)
    {
        $this->authorize('delete', $nursingNote);

        $nursingNote->delete();

        return redirect()->back()->with('success', 'Nursing note deleted successfully.');
    }
}
