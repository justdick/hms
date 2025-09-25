<?php

namespace App\Http\Controllers\Checkin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Patient;
use App\Models\PatientCheckin;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CheckinController extends Controller
{
    public function index()
    {
        // Check permission using policy
        $this->authorize('viewAny', PatientCheckin::class);

        $todayCheckins = PatientCheckin::with(['patient', 'department'])
            ->today()
            ->orderBy('checked_in_at', 'desc')
            ->get();

        $departments = Department::active()->opd()->get();

        return Inertia::render('Checkin/Index', [
            'todayCheckins' => $todayCheckins,
            'departments' => $departments,
        ]);
    }

    public function dashboard()
    {
        $this->authorize('viewAny', PatientCheckin::class);

        return $this->index();
    }

    public function store(Request $request)
    {
        $this->authorize('create', PatientCheckin::class);

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
                'error' => 'Patient is already checked in today',
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

        return redirect()->back()->with('success', 'Patient checked in successfully');
    }

    public function show(PatientCheckin $checkin)
    {
        $this->authorize('view', $checkin);

        $checkin->load([
            'patient',
            'department',
            'checkedInBy',
            'vitalSigns.recordedBy',
            'consultation.doctor',
        ]);

        return response()->json(['checkin' => $checkin]);
    }

    public function updateStatus(PatientCheckin $checkin, Request $request)
    {
        $this->authorize('update', $checkin);

        $validated = $request->validate([
            'status' => 'required|in:checked_in,vitals_taken,awaiting_consultation,in_consultation,completed,cancelled',
            'notes' => 'nullable|string',
        ]);

        $checkin->update($validated);

        return response()->json([
            'checkin' => $checkin->fresh(['patient', 'department']),
            'message' => 'Check-in status updated successfully',
        ]);
    }

    public function todayCheckins()
    {
        $this->authorize('viewAny', PatientCheckin::class);

        $checkins = PatientCheckin::with(['patient', 'department', 'checkedInBy'])
            ->today()
            ->orderBy('checked_in_at', 'desc')
            ->get();

        return response()->json(['checkins' => $checkins]);
    }

    public function departmentQueue(Department $department)
    {
        $this->authorize('viewAny', PatientCheckin::class);

        $checkins = PatientCheckin::with(['patient', 'vitalSigns'])
            ->where('department_id', $department->id)
            ->today()
            ->whereIn('status', ['checked_in', 'vitals_taken', 'awaiting_consultation'])
            ->orderBy('checked_in_at')
            ->get();

        return response()->json([
            'department' => $department,
            'queue' => $checkins,
        ]);
    }
}
