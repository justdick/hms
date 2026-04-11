<?php

namespace App\Http\Controllers\MinorProcedure;

use App\Events\MinorProcedurePerformed;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMinorProcedureRequest;
use App\Models\Department;
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

        $search = $request->input('search');
        $queueSearch = $request->input('queue_search');
        $completedSearch = $request->input('completed_search');
        $perPage = $request->input('per_page', 5);

        // Date filtering - default to today
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        if (! $dateFrom && ! $dateTo) {
            $dateFrom = now()->toDateString();
            $dateTo = now()->toDateString();
        }

        // Get Minor Procedures department
        $minorProceduresDept = Department::where('code', 'ZOOM')->first();
        $deptId = $minorProceduresDept?->id;

        // --- Queue: patients checked into Minor Procedures, not yet completed ---
        $queueQuery = PatientCheckin::with([
            'patient:id,patient_number,first_name,last_name,date_of_birth,phone_number',
            'patient.activeInsurance.plan.provider:id,name,code',
            'department:id,name',
            'vitalSigns' => fn ($q) => $q->latest()->limit(1),
        ])
            ->when($deptId, fn ($q) => $q->where('department_id', $deptId))
            ->whereNotIn('status', ['completed', 'cancelled']);

        // Apply date filter to queue
        if ($dateFrom) {
            $queueQuery->whereDate('service_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $queueQuery->whereDate('service_date', '<=', $dateTo);
        }

        // Apply search filter (search tab)
        if ($search && strlen($search) >= 2) {
            $queueQuery->whereHas('patient', function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('patient_number', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        // Apply queue search filter (queue tab)
        if ($queueSearch && strlen($queueSearch) >= 2) {
            $queueQuery->whereHas('patient', function ($query) use ($queueSearch) {
                $query->where('first_name', 'like', "%{$queueSearch}%")
                    ->orWhere('last_name', 'like', "%{$queueSearch}%")
                    ->orWhere('patient_number', 'like', "%{$queueSearch}%")
                    ->orWhere('phone_number', 'like', "%{$queueSearch}%");
            });
        }

        $queuePatients = $queueQuery->orderBy('checked_in_at')
            ->paginate($perPage, ['*'], 'queue_page')
            ->withQueryString();

        // --- Completed: minor procedures that are done ---
        $completedQuery = MinorProcedure::with([
            'patientCheckin.patient:id,patient_number,first_name,last_name,date_of_birth,phone_number',
            'patientCheckin.patient.activeInsurance.plan.provider:id,name,code',
            'patientCheckin.department:id,name',
            'patientCheckin.vitalSigns' => fn ($q) => $q->latest()->limit(1),
            'nurse:id,name',
            'procedureType:id,name,code',
            'diagnoses:id,diagnosis,code,icd_10',
            'supplies.drug:id,name,generic_name,brand_name,drug_code,form,strength,unit_price,unit_type',
        ])
            ->accessibleTo($user)
            ->where('status', 'completed');

        // Apply date filter to completed
        if ($dateFrom) {
            $completedQuery->whereDate('performed_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $completedQuery->whereDate('performed_at', '<=', $dateTo);
        }

        // Apply search filter (search tab)
        if ($search && strlen($search) >= 2) {
            $completedQuery->whereHas('patientCheckin.patient', function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('patient_number', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        // Apply completed search filter (completed tab)
        if ($completedSearch && strlen($completedSearch) >= 2) {
            $completedQuery->whereHas('patientCheckin.patient', function ($query) use ($completedSearch) {
                $query->where('first_name', 'like', "%{$completedSearch}%")
                    ->orWhere('last_name', 'like', "%{$completedSearch}%")
                    ->orWhere('patient_number', 'like', "%{$completedSearch}%")
                    ->orWhere('phone_number', 'like', "%{$completedSearch}%");
            });
        }

        $completedProcedures = $completedQuery->orderBy('performed_at', 'desc')
            ->paginate($perPage, ['*'], 'completed_page')
            ->withQueryString();

        return Inertia::render('MinorProcedure/Index', [
            'queuePatients' => $queuePatients,
            'completedProcedures' => $completedProcedures,
            'totalQueueCount' => $queuePatients->total(),
            'totalCompletedCount' => $completedProcedures->total(),
            'procedureTypes' => MinorProcedureType::active()->orderBy('name')->get(),
            'availableDrugs' => Drug::active()->orderBy('name')->get(['id', 'name', 'generic_name', 'brand_name', 'drug_code', 'form', 'strength', 'unit_price', 'unit_type']),
            'canManageTypes' => $user->can('minor-procedures.view-types'),
            'filters' => [
                'search' => $search,
                'queue_search' => $queueSearch,
                'completed_search' => $completedSearch,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => (int) $perPage,
            ],
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

        return redirect()->back()
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

    public function update(StoreMinorProcedureRequest $request, MinorProcedure $minorProcedure)
    {
        $this->authorize('view', $minorProcedure);

        $validated = $request->validated();

        $minorProcedure->update([
            'minor_procedure_type_id' => $validated['minor_procedure_type_id'],
            'procedure_notes' => $validated['procedure_notes'],
        ]);

        // Sync diagnoses
        $minorProcedure->diagnoses()->sync($validated['diagnoses'] ?? []);

        // Sync supplies: remove old, add new
        $minorProcedure->supplies()->delete();
        if (! empty($validated['supplies'])) {
            foreach ($validated['supplies'] as $supply) {
                MinorProcedureSupply::create([
                    'minor_procedure_id' => $minorProcedure->id,
                    'drug_id' => $supply['drug_id'],
                    'quantity' => $supply['quantity'],
                    'dispensed' => false,
                ]);
            }
        }

        return redirect()->back()
            ->with('success', 'Procedure updated successfully.');
    }
}
