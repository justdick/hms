<?php

namespace App\Http\Controllers\Ward;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\PatientAdmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BedAssignmentController extends Controller
{
    public function create(PatientAdmission $admission): Response
    {
        $admission->load(['patient', 'ward', 'bed']);

        // Get available beds in the ward
        $availableBeds = Bed::query()
            ->where('ward_id', $admission->ward_id)
            ->available()
            ->get();

        // Get all beds for reference (including occupied ones)
        $allBeds = Bed::query()
            ->where('ward_id', $admission->ward_id)
            ->where('is_active', true)
            ->with('currentAdmission.patient')
            ->orderBy('bed_number')
            ->get();

        return Inertia::render('Ward/BedAssignment', [
            'admission' => $admission,
            'availableBeds' => $availableBeds,
            'allBeds' => $allBeds,
            'hasAvailableBeds' => $availableBeds->isNotEmpty(),
        ]);
    }

    public function store(Request $request, PatientAdmission $admission): RedirectResponse
    {
        $validated = $request->validate([
            'bed_id' => 'nullable|exists:beds,id',
            'notes' => 'nullable|string|max:1000',
            'mark_as_overflow' => 'boolean',
        ]);

        // If assigning a bed
        if (isset($validated['bed_id'])) {
            $bed = Bed::findOrFail($validated['bed_id']);

            // Validate bed is in the same ward
            if ($bed->ward_id !== $admission->ward_id) {
                return back()->withErrors([
                    'bed_id' => 'The selected bed is not in the same ward.',
                ]);
            }

            // Validate bed is available
            if ($bed->status !== 'available') {
                return back()->withErrors([
                    'bed_id' => 'The selected bed is not available.',
                ]);
            }

            $admission->assignBed($bed, $request->user(), $validated['notes'] ?? null);

            return redirect()
                ->route('wards.patients.show', ['ward' => $admission->ward_id, 'admission' => $admission->id])
                ->with('success', "Bed {$bed->bed_number} has been assigned to the patient.");
        }

        // If marking as overflow (no bed available)
        if ($validated['mark_as_overflow'] ?? false) {
            $admission->markAsOverflow($validated['notes'] ?? 'No beds available at time of admission');

            return redirect()
                ->route('wards.patients.show', ['ward' => $admission->ward_id, 'admission' => $admission->id])
                ->with('warning', 'Patient has been marked as overflow. Please assign a bed as soon as one becomes available.');
        }

        return back()->withErrors([
            'bed_id' => 'Please select a bed or mark the patient as overflow.',
        ]);
    }

    public function update(Request $request, PatientAdmission $admission): RedirectResponse
    {
        $validated = $request->validate([
            'bed_id' => 'required|exists:beds,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $newBed = Bed::findOrFail($validated['bed_id']);

        // Validate bed is in the same ward
        if ($newBed->ward_id !== $admission->ward_id) {
            return back()->withErrors([
                'bed_id' => 'The selected bed is not in the same ward.',
            ]);
        }

        // Validate bed is available (unless it's the same bed)
        if ($newBed->id !== $admission->bed_id && $newBed->status !== 'available') {
            return back()->withErrors([
                'bed_id' => 'The selected bed is not available.',
            ]);
        }

        $oldBedNumber = $admission->bed?->bed_number;
        $admission->changeBed($newBed, $request->user(), $validated['notes'] ?? null);

        return redirect()
            ->route('wards.patients.show', ['ward' => $admission->ward_id, 'admission' => $admission->id])
            ->with('success', "Patient bed changed from {$oldBedNumber} to {$newBed->bed_number}.");
    }

    public function destroy(Request $request, PatientAdmission $admission): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => 'required|string|max:1000',
        ]);

        if ($admission->bed) {
            $bedNumber = $admission->bed->bed_number;
            $admission->bed->markAsAvailable();
        }

        $admission->update([
            'bed_id' => null,
            'bed_assigned_by_id' => null,
            'bed_assigned_at' => null,
        ]);

        $admission->markAsOverflow($validated['notes']);

        return redirect()
            ->route('wards.patients.show', ['ward' => $admission->ward_id, 'admission' => $admission->id])
            ->with('warning', "Patient removed from bed {$bedNumber}. Marked as overflow.");
    }
}
