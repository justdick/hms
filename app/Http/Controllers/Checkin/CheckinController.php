<?php

namespace App\Http\Controllers\Checkin;

use App\Events\PatientCheckedIn;
use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\Department;
use App\Models\Patient;
use App\Models\PatientCheckin;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CheckinController extends Controller
{
    public function index(Request $request)
    {
        // Check permission using policy
        $this->authorize('viewAny', PatientCheckin::class);

        $user = auth()->user();

        // Show today's completed check-ins and all incomplete check-ins
        // FIFO ordering: oldest first (lower ID = checked in earlier)
        $todayCheckins = PatientCheckin::with(['patient', 'department'])
            ->where(function ($query) {
                $query->whereDate('checked_in_at', today())
                    ->orWhereIn('status', ['checked_in', 'vitals_taken', 'awaiting_consultation', 'in_consultation']);
            })
            ->when(! $user->can('viewAnyDepartment', PatientCheckin::class), function ($query) use ($user) {
                // Restrict to user's departments if they don't have cross-department permission
                $query->whereIn('department_id', $user->departments->pluck('id'));
            })
            ->orderBy('id', 'asc')
            ->get();

        // Get departments based on permissions
        $departments = $user->can('viewAnyDepartment', PatientCheckin::class)
            ? Department::active()->opd()->get()
            : $user->departments()->active()->opd()->get();

        return Inertia::render('Checkin/Index', [
            'todayCheckins' => $todayCheckins,
            'departments' => $departments,
            'permissions' => [
                'canViewAnyDate' => $user->can('viewAnyDate', PatientCheckin::class),
                'canViewAnyDepartment' => $user->can('viewAnyDepartment', PatientCheckin::class),
                'canUpdateDate' => $user->can('checkins.update-date'),
            ],
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

        $patient = Patient::find($validated['patient_id']);

        // Check if patient is currently admitted
        $activeAdmission = $patient->activeAdmission;
        if ($activeAdmission) {
            return back()->withErrors([
                'patient_id' => 'Patient is currently admitted and cannot check in for outpatient services.',
            ])->withInput();
        }

        // Auto-complete any in-progress consultations for this patient
        Consultation::whereHas('patientCheckin', function ($query) use ($validated) {
            $query->where('patient_id', $validated['patient_id']);
        })
            ->where('status', 'in_progress')
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

        // Check if patient has an incomplete check-in (regardless of date)
        $incompleteCheckin = PatientCheckin::where('patient_id', $validated['patient_id'])
            ->whereIn('status', ['checked_in', 'vitals_taken', 'awaiting_consultation', 'in_consultation'])
            ->first();

        if ($incompleteCheckin) {
            return back()->withErrors([
                'patient_id' => "Patient has an incomplete check-in (Status: {$incompleteCheckin->status}) that needs to be completed or cancelled first.",
            ])->with('existing_checkin', [
                'id' => $incompleteCheckin->id,
                'checked_in_at' => $incompleteCheckin->checked_in_at,
                'status' => $incompleteCheckin->status,
            ])->withInput();
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

        // Fire check-in event for billing
        event(new PatientCheckedIn($checkin));

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

        // Show all incomplete check-ins for this department regardless of date
        // FIFO ordering: oldest check-in first by ID
        $checkins = PatientCheckin::with(['patient', 'vitalSigns'])
            ->where('department_id', $department->id)
            ->whereIn('status', ['checked_in', 'vitals_taken', 'awaiting_consultation'])
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'department' => $department,
            'queue' => $checkins,
        ]);
    }

    public function cancel(PatientCheckin $checkin, Request $request)
    {
        $this->authorize('cancel', $checkin);

        // Note: Policy already checks if check-in can be cancelled
        // But we keep this validation for explicit error message
        if ($checkin->isCompleted() || $checkin->isCancelled()) {
            return back()->withErrors([
                'error' => 'Cannot cancel a completed or already cancelled check-in.',
            ]);
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        // Cancel the check-in (this will also void pending charges)
        $checkin->cancel($validated['reason'] ?? null);

        return redirect()->back()->with('success', 'Check-in cancelled successfully. Any unpaid charges have been voided.');
    }

    public function updateDepartment(PatientCheckin $checkin, Request $request)
    {
        $this->authorize('update', $checkin);

        // Only allow department change if check-in hasn't started consultation
        if ($checkin->status === 'in_consultation') {
            return back()->withErrors([
                'error' => 'Cannot change department during consultation. Doctor should use transfer functionality.',
            ]);
        }

        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
        ]);

        $checkin->update([
            'department_id' => $validated['department_id'],
        ]);

        return redirect()->back()->with('success', 'Department updated successfully.');
    }

    public function search(Request $request)
    {
        $this->authorize('viewAny', PatientCheckin::class);

        $user = auth()->user();

        // Check if user can view any date
        if (! $user->can('viewAnyDate', PatientCheckin::class)) {
            return response()->json([
                'error' => 'You do not have permission to view historical check-ins.',
            ], 403);
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $query = PatientCheckin::with(['patient', 'department', 'checkedInBy', 'vitalSigns'])
            ->whereDate('checked_in_at', $validated['date']);

        // Apply department filter if provided
        if (! empty($validated['department_id'])) {
            $query->where('department_id', $validated['department_id']);
        }

        // Restrict to user's departments if they don't have cross-department permission
        if (! $user->can('viewAnyDepartment', PatientCheckin::class)) {
            $query->whereIn('department_id', $user->departments->pluck('id'));
        }

        $checkins = $query->orderBy('checked_in_at', 'desc')->get();

        return response()->json([
            'checkins' => $checkins,
            'date' => $validated['date'],
            'department_id' => $validated['department_id'] ?? null,
        ]);
    }

    public function updateDate(PatientCheckin $checkin, Request $request)
    {
        $this->authorize('updateDate', $checkin);

        $validated = $request->validate([
            'checked_in_at' => 'required|date|before_or_equal:today',
        ]);

        $checkin->update([
            'checked_in_at' => $validated['checked_in_at'],
        ]);

        return redirect()->back()->with('success', 'Check-in date updated successfully.');
    }
}
