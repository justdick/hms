<?php

namespace App\Http\Controllers\MinorProcedure;

use App\Events\MinorProcedurePerformed;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMinorProcedureRequest;
use App\Models\Drug;
use App\Models\MinorProcedure;
use App\Models\MinorProcedureSupply;
use App\Models\MinorProcedureType;
use App\Models\PatientCheckin;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MinorProcedureController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', MinorProcedure::class);

        $user = $request->user();

        // Get Minor Procedures department
        $minorProceduresDept = \App\Models\Department::where('code', 'MINPROC')->first();

        // Get count of patients in Minor Procedures queue
        $queueCount = 0;
        if ($minorProceduresDept) {
            $queueCount = PatientCheckin::accessibleTo($user)
                ->where('department_id', $minorProceduresDept->id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count();
        }

        return Inertia::render('MinorProcedure/Index', [
            'queueCount' => $queueCount,
            'procedureTypes' => MinorProcedureType::active()->orderBy('name')->get(),
            'availableDrugs' => Drug::active()->orderBy('name')->get(['id', 'name', 'generic_name', 'brand_name', 'drug_code', 'form', 'strength', 'unit_price', 'unit_type']),
            // Diagnoses loaded via async search - too many to load upfront
            'canManageTypes' => $user->can('minor-procedures.view-types'),
        ]);
    }

    public function search(Request $request)
    {
        $this->authorize('viewAny', MinorProcedure::class);

        $user = $request->user();
        $search = $request->input('search');

        // Validate search input
        if (! $search || strlen($search) < 2) {
            return response()->json([
                'patients' => [],
            ]);
        }

        // Get Minor Procedures department ID
        $minorProceduresDept = \App\Models\Department::where('code', 'MINPROC')->first();

        if (! $minorProceduresDept) {
            return response()->json([
                'patients' => [],
                'error' => 'Minor Procedures department not found',
            ]);
        }

        // Search patients in Minor Procedures queue
        $patients = PatientCheckin::with([
            'patient:id,patient_number,first_name,last_name,date_of_birth,phone_number',
            'department:id,name',
            'vitalSigns' => function ($query) {
                $query->latest()->limit(1);
            },
        ])
            ->accessibleTo($user)
            ->where('department_id', $minorProceduresDept->id)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereHas('patient', function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('patient_number', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            })
            ->orderBy('checked_in_at')
            ->get();

        return response()->json([
            'patients' => $patients,
        ]);
    }

    public function store(StoreMinorProcedureRequest $request)
    {
        $this->authorize('create', MinorProcedure::class);

        $validated = $request->validated();

        $patientCheckin = PatientCheckin::findOrFail($validated['patient_checkin_id']);

        // Verify user has access to this department
        $user = $request->user();
        if (! $user->hasRole('Admin') && ! $user->can('minor-procedures.view-all')) {
            if (! $user->departments->contains($patientCheckin->department_id)) {
                abort(403, 'You do not have access to this department.');
            }
        }

        // Create the minor procedure
        $procedure = MinorProcedure::create([
            'patient_checkin_id' => $patientCheckin->id,
            'nurse_id' => $request->user()->id,
            'minor_procedure_type_id' => $validated['minor_procedure_type_id'],
            'procedure_notes' => $validated['procedure_notes'],
            'performed_at' => now(),
            'status' => 'completed',
        ]);

        // Attach diagnoses if provided
        if (! empty($validated['diagnoses'])) {
            $procedure->diagnoses()->attach($validated['diagnoses']);
        }

        // Create supply requests if provided
        if (! empty($validated['supplies'])) {
            foreach ($validated['supplies'] as $supply) {
                MinorProcedureSupply::create([
                    'minor_procedure_id' => $procedure->id,
                    'drug_id' => $supply['drug_id'],
                    'quantity' => $supply['quantity'],
                    'dispensed' => false,
                ]);
            }
        }

        // Dispatch event for billing
        event(new MinorProcedurePerformed($procedure));

        // Update check-in status to completed
        $patientCheckin->update([
            'status' => 'completed',
        ]);

        return redirect()->route('minor-procedures.index')
            ->with('success', 'Procedure completed successfully.');
    }

    public function show(MinorProcedure $minorProcedure)
    {
        $this->authorize('view', $minorProcedure);

        $minorProcedure->load([
            'patientCheckin.patient',
            'patientCheckin.department',
            'nurse:id,name',
            'procedureType',
            'diagnoses',
            'supplies.drug',
            'supplies.dispenser:id,name',
        ]);

        return Inertia::render('MinorProcedure/Show', [
            'procedure' => $minorProcedure,
        ]);
    }
}
