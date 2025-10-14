<?php

namespace App\Http\Controllers\Consultation;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\Department;
use Illuminate\Http\Request;

class ConsultationTransferController extends Controller
{
    public function store(Consultation $consultation, Request $request)
    {
        $this->authorize('update', $consultation);

        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'reason' => 'nullable|string|max:500',
        ]);

        // Get current department
        $currentDepartment = $consultation->patientCheckin->department;
        $newDepartment = Department::findOrFail($validated['department_id']);

        // Prevent transfer to same department
        if ($currentDepartment->id === $newDepartment->id) {
            return back()->withErrors([
                'department_id' => 'Patient is already in this department.',
            ]);
        }

        // Update check-in department
        $consultation->patientCheckin->update([
            'department_id' => $validated['department_id'],
        ]);

        // Add transfer note to consultation
        $transferNote = "\n\n--- Transfer ---\n";
        $transferNote .= "Transferred from {$currentDepartment->name} to {$newDepartment->name}\n";
        $transferNote .= 'Date: '.now()->format('Y-m-d H:i:s')."\n";
        if ($validated['reason']) {
            $transferNote .= "Reason: {$validated['reason']}\n";
        }

        $consultation->update([
            'plan_notes' => ($consultation->plan_notes ?? '').$transferNote,
        ]);

        return redirect()->back()->with('success', "Patient transferred to {$newDepartment->name} successfully.");
    }
}
