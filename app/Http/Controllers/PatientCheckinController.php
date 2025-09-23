<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PatientCheckin;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PatientCheckinController extends Controller
{
    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('opd.checkin.create'), 403);

        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'department_id' => 'required|exists:departments,id',
            'notes' => 'nullable|string',
        ]);

        // Check if patient already checked in today
        $existingCheckin = PatientCheckin::where('patient_id', $validated['patient_id'])
            ->today()
            ->first();

        if ($existingCheckin) {
            return response()->json([
                'error' => 'Patient is already checked in today'
            ], 422);
        }

        $checkin = PatientCheckin::create([
            'patient_id' => $validated['patient_id'],
            'department_id' => $validated['department_id'],
            'checked_in_by' => auth()->id(),
            'checked_in_at' => now(),
            'status' => 'checked_in',
            'notes' => $validated['notes'] ?? null,
        ]);

        $checkin->load(['patient', 'department', 'checkedInBy']);

        return response()->json([
            'checkin' => $checkin,
            'message' => 'Patient checked in successfully'
        ]);
    }

    public function show(PatientCheckin $checkin)
    {
        abort_unless(auth()->user()->can('opd.checkin.view'), 403);

        $checkin->load([
            'patient',
            'department',
            'checkedInBy',
            'vitalSigns.recordedBy',
            'consultation.doctor'
        ]);

        return response()->json(['checkin' => $checkin]);
    }

    public function updateStatus(PatientCheckin $checkin, Request $request)
    {
        abort_unless(auth()->user()->can('opd.checkin.manage'), 403);

        $validated = $request->validate([
            'status' => 'required|in:checked_in,vitals_taken,awaiting_consultation,in_consultation,completed,cancelled',
            'notes' => 'nullable|string',
        ]);

        $checkin->update($validated);

        return response()->json([
            'checkin' => $checkin->fresh(['patient', 'department']),
            'message' => 'Check-in status updated successfully'
        ]);
    }

    public function todayCheckins()
    {
        abort_unless(auth()->user()->can('opd.checkin.view'), 403);

        $checkins = PatientCheckin::with(['patient', 'department', 'checkedInBy'])
            ->today()
            ->orderBy('checked_in_at', 'desc')
            ->get();

        return response()->json(['checkins' => $checkins]);
    }

    public function departmentQueue(Department $department)
    {
        abort_unless(auth()->user()->can('opd.checkin.view'), 403);

        $checkins = PatientCheckin::with(['patient', 'vitalSigns'])
            ->where('department_id', $department->id)
            ->today()
            ->whereIn('status', ['checked_in', 'vitals_taken', 'awaiting_consultation'])
            ->orderBy('checked_in_at')
            ->get();

        return response()->json([
            'department' => $department,
            'queue' => $checkins
        ]);
    }
}
