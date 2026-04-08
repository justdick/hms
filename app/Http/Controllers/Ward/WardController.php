<?php

namespace App\Http\Controllers\Ward;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\Ward;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WardController extends Controller
{
    public function index()
    {
        $wards = Ward::with(['beds:id,ward_id,status'])
            ->withCount(['admissions as admitted_patients_count' => function ($query) {
                $query->where('status', 'admitted');
            }])
            ->orderBy('name')
            ->get();

        return Inertia::render('Ward/Index', [
            'wards' => $wards,
        ]);
    }

    public function create()
    {
        return Inertia::render('Ward/Create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:wards,code',
            'description' => 'nullable|string|max:500',
            'bed_count' => 'required|integer|min:1|max:100',
        ]);

        $ward = Ward::create([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'total_beds' => $request->bed_count,
            'available_beds' => $request->bed_count,
            'is_active' => true,
        ]);

        // Create beds for the ward
        for ($i = 1; $i <= $request->bed_count; $i++) {
            Bed::create([
                'bed_number' => str_pad($i, 2, '0', STR_PAD_LEFT),
                'ward_id' => $ward->id,
                'status' => 'available',
                'type' => 'standard',
                'is_active' => true,
            ]);
        }

        return redirect()->route('wards.index')
            ->with('success', 'Ward created successfully with '.$request->bed_count.' beds.');
    }

    public function show(Request $request, Ward $ward)
    {
        $ward->load(['beds:id,ward_id,bed_number,status,type,is_active']);

        $search = $request->input('search');
        $perPage = $request->input('per_page', 10);
        $status = $request->input('status', 'admitted');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $datePreset = $request->input('date_preset');
        $insuranceType = $request->input('insurance_type');

        // Default to this_month if no date filter provided
        if (! $dateFrom && ! $dateTo && ! $datePreset) {
            $datePreset = 'this_month';
            $dateFrom = now()->startOfMonth()->toDateString();
            $dateTo = now()->toDateString();
        }

        // Build admissions query with pagination
        $admissionsQuery = $ward->admissions()
            ->with([
                'patient:id,first_name,last_name,date_of_birth,gender,patient_number',
                'patient.activeInsurance.plan.provider',
                'patient.activeAdmissions:id,patient_id,ward_id,admitted_at',
                'patient.activeAdmissions.ward:id,name',
                'bed:id,bed_number',
                'consultation.doctor:id,name',
                'todayMedicationAdministrations' => function ($q) {
                    $q->whereDate('administered_at', today())
                        ->with('prescription.drug');
                },
            ])
            ->withCount(['wardRounds', 'nursingNotes']);

        // Apply status filter (defaults to 'admitted')
        if ($status && $status !== 'all') {
            $admissionsQuery->where('status', $status);
        }

        // Apply date filter on admitted_at
        if ($dateFrom) {
            $admissionsQuery->whereDate('admitted_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $admissionsQuery->whereDate('admitted_at', '<=', $dateTo);
        }

        // Apply insurance type filter
        // Filter by actual claim existence on the check-in (not just patient insurance profile)
        // This ensures only patients who were actually checked in with NHIA (CCC provided) are counted
        if ($insuranceType === 'nhia') {
            $admissionsQuery->whereHas('consultation.patientCheckin.insuranceClaim', function ($q) {
                $q->whereHas('patientInsurance.plan.provider', function ($q2) {
                    $q2->where('is_nhis', true);
                });
            });
        } elseif ($insuranceType === 'non_nhia') {
            $admissionsQuery->whereDoesntHave('consultation.patientCheckin.insuranceClaim', function ($q) {
                $q->whereHas('patientInsurance.plan.provider', function ($q2) {
                    $q2->where('is_nhis', true);
                });
            });
        }

        // Apply search filter
        if ($search) {
            $admissionsQuery->where(function ($query) use ($search) {
                $query->where('admission_number', 'like', "%{$search}%")
                    ->orWhereHas('patient', function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('patient_number', 'like', "%{$search}%");
                    });
            });
        }

        $admissionsQuery->orderBy('admitted_at', 'desc');

        // Paginate admissions
        $paginatedAdmissions = $admissionsQuery->paginate($perPage)->withQueryString();

        // Transform paginated data
        $transformedData = collect($paginatedAdmissions->items())->map(function ($admission) {
            return $admission->toArray();
        });

        // Stats are always based on currently admitted patients (real-time operational data)
        $allAdmissions = $ward->admissions()
            ->where('status', 'admitted')
            ->with([
                'consultation.patientCheckin.insuranceClaim.patientInsurance.plan.provider',
                'todayMedicationAdministrations' => function ($q) {
                    $q->whereDate('administered_at', today());
                },
            ])
            ->get();

        // Calculate ward statistics from currently admitted patients
        // Count NHIA based on actual claim existence, not just patient insurance profile
        $nhiaCount = $allAdmissions->filter(function ($admission) {
            return $admission->consultation?->patientCheckin?->insuranceClaim?->patientInsurance?->plan?->provider?->is_nhis ?? false;
        })->count();

        $stats = [
            'total_patients' => $allAdmissions->count(),
            'nhia_patients' => $nhiaCount,
            'non_nhia_patients' => $allAdmissions->count() - $nhiaCount,
            'pending_meds_count' => $allAdmissions->sum(function ($admission) {
                return $admission->todayMedicationAdministrations->where('status', 'pending')->count();
            }),
        ];

        return Inertia::render('Ward/Show', [
            'ward' => $ward,
            'stats' => $stats,
            'admissions' => [
                'data' => $transformedData->values()->all(),
                'current_page' => $paginatedAdmissions->currentPage(),
                'from' => $paginatedAdmissions->firstItem(),
                'last_page' => $paginatedAdmissions->lastPage(),
                'per_page' => $paginatedAdmissions->perPage(),
                'to' => $paginatedAdmissions->lastItem(),
                'total' => $paginatedAdmissions->total(),
                'links' => $paginatedAdmissions->linkCollection()->toArray(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'date_preset' => $datePreset,
                'insurance_type' => $insuranceType,
            ],
        ]);
    }

    public function edit(Ward $ward)
    {
        return Inertia::render('Ward/Edit', [
            'ward' => $ward,
        ]);
    }

    public function update(Request $request, Ward $ward)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:wards,code,'.$ward->id,
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        $ward->update([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('wards.index')
            ->with('success', 'Ward updated successfully.');
    }

    public function destroy(Ward $ward)
    {
        // Check if ward has active admissions
        $activeAdmissions = $ward->admissions()->where('status', 'admitted')->count();

        if ($activeAdmissions > 0) {
            return redirect()->back()
                ->withErrors(['ward' => 'Cannot delete ward with active patient admissions.']);
        }

        // Delete associated beds
        $ward->beds()->delete();

        // Delete ward
        $ward->delete();

        return redirect()->route('wards.index')
            ->with('success', 'Ward deleted successfully.');
    }

    public function toggleStatus(Ward $ward)
    {
        $ward->update(['is_active' => ! $ward->is_active]);

        $status = $ward->is_active ? 'activated' : 'deactivated';

        return redirect()->back()
            ->with('success', "Ward {$status} successfully.");
    }
}
