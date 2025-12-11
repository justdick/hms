<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\DispensePrescriptionRequest;
use App\Http\Requests\ReviewPrescriptionRequest;
use App\Models\Dispensing;
use App\Models\MinorProcedureSupply;
use App\Models\Patient;
use App\Models\Prescription;
use App\Services\DispensingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DispensingController extends Controller
{
    public function __construct(
        protected DispensingService $dispensingService
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Dispensing::class);

        $pendingPrescriptionsCount = Prescription::where('status', 'prescribed')
            ->where('migrated_from_mittag', false)
            ->count();
        $pendingSuppliesCount = MinorProcedureSupply::where('status', 'pending')->count();
        $totalPendingCount = $pendingPrescriptionsCount + $pendingSuppliesCount;

        return Inertia::render('Pharmacy/Dispensing/Index', [
            'pendingCount' => $totalPendingCount,
        ]);
    }

    public function search(Request $request)
    {
        $this->authorize('viewAny', Dispensing::class);

        $query = $request->input('query');
        $dateFilter = $request->input('date_filter', 'today'); // today, week, all

        if (empty($query)) {
            return response()->json([]);
        }

        // Determine date range based on filter
        $dateConstraint = match ($dateFilter) {
            'today' => now()->startOfDay(),
            'week' => now()->subDays(7)->startOfDay(),
            default => null, // 'all' - no date constraint
        };

        // Build query with search and EXISTS checks combined
        // Don't pre-limit patient search - let the EXISTS filter narrow it down first
        $patients = Patient::query()
            ->where(function ($q) use ($query) {
                $q->where('patient_number', 'like', "%{$query}%")
                    ->orWhere('phone_number', 'like', "%{$query}%")
                    ->orWhere('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%");
            })
            ->where(function ($q) use ($dateConstraint) {
                // Use EXISTS subqueries instead of nested whereHas for better performance
                $q->whereExists(function ($subquery) use ($dateConstraint) {
                    $subquery->selectRaw('1')
                        ->from('prescriptions')
                        ->join('consultations', 'prescriptions.consultation_id', '=', 'consultations.id')
                        ->join('patient_checkins', 'consultations.patient_checkin_id', '=', 'patient_checkins.id')
                        ->whereColumn('patient_checkins.patient_id', 'patients.id')
                        ->whereIn('prescriptions.status', ['prescribed', 'reviewed', 'dispensed'])
                        ->where('prescriptions.migrated_from_mittag', false);
                    if ($dateConstraint) {
                        $subquery->where('prescriptions.created_at', '>=', $dateConstraint);
                    }
                })
                    ->orWhereExists(function ($subquery) use ($dateConstraint) {
                        $subquery->selectRaw('1')
                            ->from('prescriptions')
                            ->join('ward_rounds', function ($join) {
                                $join->on('prescriptions.prescribable_id', '=', 'ward_rounds.id')
                                    ->where('prescriptions.prescribable_type', 'App\\Models\\WardRound');
                            })
                            ->join('patient_admissions', 'ward_rounds.patient_admission_id', '=', 'patient_admissions.id')
                            ->whereColumn('patient_admissions.patient_id', 'patients.id')
                            ->whereIn('prescriptions.status', ['prescribed', 'reviewed', 'dispensed'])
                            ->where('prescriptions.migrated_from_mittag', false);
                        if ($dateConstraint) {
                            $subquery->where('prescriptions.created_at', '>=', $dateConstraint);
                        }
                    })
                    ->orWhereExists(function ($subquery) use ($dateConstraint) {
                        $subquery->selectRaw('1')
                            ->from('minor_procedure_supplies')
                            ->join('minor_procedures', 'minor_procedure_supplies.minor_procedure_id', '=', 'minor_procedures.id')
                            ->join('patient_checkins', 'minor_procedures.patient_checkin_id', '=', 'patient_checkins.id')
                            ->whereColumn('patient_checkins.patient_id', 'patients.id')
                            ->whereIn('minor_procedure_supplies.status', ['pending', 'reviewed', 'dispensed']);
                        if ($dateConstraint) {
                            $subquery->where('minor_procedure_supplies.created_at', '>=', $dateConstraint);
                        }
                    });
            })
            ->with([
                // Don't filter checkins by date - only filter prescriptions by date
                // A checkin from yesterday can have prescriptions created today
                'checkins' => function ($q) use ($dateConstraint) {
                    $q->latest()
                        ->with([
                            'consultations' => function ($q) use ($dateConstraint) {
                                $q->latest()
                                    ->with(['prescriptions' => function ($q) use ($dateConstraint) {
                                        $q->whereIn('status', ['prescribed', 'reviewed', 'dispensed'])
                                            ->where('migrated_from_mittag', false)
                                            ->with('drug:id,name,form,unit_type');
                                        if ($dateConstraint) {
                                            $q->where('created_at', '>=', $dateConstraint);
                                        }
                                    }]);
                            },
                            'minorProcedures' => function ($q) use ($dateConstraint) {
                                $q->with([
                                    'procedureType:id,name,code',
                                    'supplies' => function ($q) use ($dateConstraint) {
                                        $q->whereIn('status', ['pending', 'reviewed', 'dispensed'])
                                            ->with('drug:id,name,form,unit_type');
                                        if ($dateConstraint) {
                                            $q->where('created_at', '>=', $dateConstraint);
                                        }
                                    },
                                ]);
                            },
                        ]);
                },
                'admissions' => function ($q) use ($dateConstraint) {
                    $q->whereNull('discharged_at')
                        ->with(['wardRounds' => function ($q) use ($dateConstraint) {
                            // Don't filter ward rounds by date - only filter prescriptions
                            $q->with(['prescriptions' => function ($q) use ($dateConstraint) {
                                $q->whereIn('status', ['prescribed', 'reviewed', 'dispensed'])
                                    ->where('migrated_from_mittag', false)
                                    ->with('drug:id,name,form,unit_type');
                                if ($dateConstraint) {
                                    $q->where('created_at', '>=', $dateConstraint);
                                }
                            }]);
                        }]);
                },
            ])
            ->limit(10)
            ->get()
            ->map(function ($patient) {
                // Collect all prescriptions from consultations and ward rounds
                $allPrescriptions = collect();
                $allSupplies = collect();

                // Add prescriptions from consultations
                foreach ($patient->checkins as $checkin) {
                    foreach ($checkin->consultations as $consultation) {
                        $allPrescriptions = $allPrescriptions->merge($consultation->prescriptions);
                    }

                    // Add supplies from minor procedures
                    foreach ($checkin->minorProcedures as $procedure) {
                        $allSupplies = $allSupplies->merge($procedure->supplies);
                    }
                }

                // Add prescriptions from ward rounds
                foreach ($patient->admissions as $admission) {
                    foreach ($admission->wardRounds as $wardRound) {
                        $allPrescriptions = $allPrescriptions->merge($wardRound->prescriptions);
                    }
                }

                // Count statuses for prescriptions
                $prescribedCount = $allPrescriptions->where('status', 'prescribed')->count();
                $prescriptionReviewedCount = $allPrescriptions->where('status', 'reviewed')->count();
                $prescriptionDispensedCount = $allPrescriptions->where('status', 'dispensed')->count();

                // Count statuses for supplies
                $supplyPendingCount = $allSupplies->where('status', 'pending')->count();
                $supplyReviewedCount = $allSupplies->where('status', 'reviewed')->count();
                $supplyDispensedCount = $allSupplies->where('status', 'dispensed')->count();

                // Combined counts
                $totalNeedsReview = $prescribedCount + $supplyPendingCount;
                $totalReadyToDispense = $prescriptionReviewedCount + $supplyReviewedCount;
                $totalDispensed = $prescriptionDispensedCount + $supplyDispensedCount;
                $totalItems = $allPrescriptions->count() + $allSupplies->count();

                // Determine overall status
                $status = 'completed'; // default
                if ($totalNeedsReview > 0) {
                    $status = 'needs_review';
                } elseif ($totalReadyToDispense > 0) {
                    $status = 'ready_to_dispense';
                } elseif ($totalDispensed > 0 && $totalDispensed === $totalItems) {
                    $status = 'completed';
                }

                return [
                    'id' => $patient->id,
                    'patient_number' => $patient->patient_number,
                    'full_name' => $patient->full_name,
                    'phone_number' => $patient->phone_number,
                    'status' => $status,
                    // Prescription counts
                    'prescription_count' => $allPrescriptions->count(),
                    'prescription_needs_review' => $prescribedCount,
                    'prescription_ready_to_dispense' => $prescriptionReviewedCount,
                    'prescription_dispensed' => $prescriptionDispensedCount,
                    // Supply counts
                    'supply_count' => $allSupplies->count(),
                    'supply_needs_review' => $supplyPendingCount,
                    'supply_ready_to_dispense' => $supplyReviewedCount,
                    'supply_dispensed' => $supplyDispensedCount,
                    // Combined counts
                    'total_items' => $totalItems,
                    'total_needs_review' => $totalNeedsReview,
                    'total_ready_to_dispense' => $totalReadyToDispense,
                ];
            })
            ->filter(fn ($patient) => $patient['total_items'] > 0); // Only include patients with items

        return response()->json($patients->values());
    }

    public function show(Patient $patient, Request $request)
    {
        $this->authorize('viewAny', Dispensing::class);

        $dateFilter = $request->input('date_filter', 'today');

        // Determine date range based on filter
        $dateConstraint = match ($dateFilter) {
            'today' => now()->startOfDay(),
            'week' => now()->subDays(7)->startOfDay(),
            default => null, // 'all' - no date constraint
        };

        // Get both prescriptions and supplies for review
        $prescriptionsData = $this->dispensingService->getPrescriptionsForReview($patient->id, $dateConstraint);
        $suppliesData = $this->dispensingService->getSuppliesForReview($patient->id, $dateConstraint);

        return response()->json([
            'prescriptions' => $prescriptionsData,
            'supplies' => $suppliesData,
        ]);
    }

    /**
     * Get prescriptions for dispensing modal
     */
    public function getPrescriptionsForDispensing(Patient $patient, Request $request)
    {
        $this->authorize('viewAny', Dispensing::class);

        $dateFilter = $request->input('date_filter', 'today');

        // Determine date range based on filter
        $dateConstraint = match ($dateFilter) {
            'today' => now()->startOfDay(),
            'week' => now()->subDays(7)->startOfDay(),
            default => null, // 'all' - no date constraint
        };

        // Get both prescriptions and supplies ready for dispensing
        $prescriptionsData = $this->dispensingService->getPrescriptionsForDispensing($patient->id, $dateConstraint);
        $suppliesData = $this->dispensingService->getSuppliesForDispensing($patient->id, $dateConstraint);

        return response()->json([
            'prescriptionsData' => $prescriptionsData,
            'suppliesData' => $suppliesData,
        ]);
    }

    /**
     * Touchpoint 1: Process review changes (from modal) - handles both prescriptions and supplies.
     */
    public function updateReview(ReviewPrescriptionRequest $request, Patient $patient): RedirectResponse
    {
        $this->authorize('viewAny', Dispensing::class);

        $validated = $request->validated();

        // Review prescriptions
        if (isset($validated['reviews'])) {
            foreach ($validated['reviews'] as $reviewData) {
                $prescription = Prescription::findOrFail($reviewData['prescription_id']);

                $this->dispensingService->reviewPrescription(
                    $prescription,
                    [
                        'action' => $reviewData['action'],
                        'quantity_to_dispense' => $reviewData['quantity_to_dispense'] ?? null,
                        'notes' => $reviewData['notes'] ?? null,
                        'reason' => $reviewData['reason'] ?? null,
                    ],
                    auth()->user()
                );
            }
        }

        // Review supplies
        if (isset($validated['supply_reviews'])) {
            foreach ($validated['supply_reviews'] as $reviewData) {
                $supply = MinorProcedureSupply::findOrFail($reviewData['supply_id']);

                $this->dispensingService->reviewSupply(
                    $supply,
                    [
                        'action' => $reviewData['action'],
                        'quantity_to_dispense' => $reviewData['quantity_to_dispense'] ?? null,
                        'notes' => $reviewData['notes'] ?? null,
                        'reason' => $reviewData['reason'] ?? null,
                    ],
                    auth()->user()
                );
            }
        }

        return redirect()
            ->route('pharmacy.dispensing.index')
            ->with('success', 'Items reviewed successfully. Patient can now proceed to payment and dispensing.');
    }

    /**
     * Touchpoint 2: Show dispense page.
     */
    public function showDispense(Patient $patient): Response
    {
        $this->authorize('create', Dispensing::class);

        $latestCheckin = $patient->checkins()->latest()->first();

        if (! $latestCheckin) {
            abort(404, 'No checkin found for this patient.');
        }

        $prescriptionsData = $this->dispensingService->getPrescriptionsForDispensing($latestCheckin->id);

        return Inertia::render('Pharmacy/Dispensing/Dispense', [
            'patient' => $patient->load('checkins:id,patient_id,created_at'),
            'checkin' => $latestCheckin,
            'prescriptionsData' => $prescriptionsData,
        ]);
    }

    /**
     * Touchpoint 2: Process dispensing.
     */
    public function processDispensing(DispensePrescriptionRequest $request, Prescription $prescription): RedirectResponse
    {
        $this->authorize('create', Dispensing::class);

        try {
            $validated = $request->validated();

            $this->dispensingService->dispensePrescription(
                $prescription,
                [
                    'notes' => $validated['notes'] ?? null,
                ],
                auth()->user()
            );

            return redirect()
                ->route('pharmacy.dispensing.index')
                ->with('success', 'Medication dispensed successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Search for patients with pending minor procedure supplies.
     */
    protected function searchSupplies(string $query, string $dateFilter)
    {
        // Determine date range based on filter
        $dateConstraint = match ($dateFilter) {
            'today' => now()->startOfDay(),
            'week' => now()->subDays(7)->startOfDay(),
            default => null, // 'all' - no date constraint
        };

        $patients = Patient::query()
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('patient_number', 'like', "%{$query}%")
                    ->orWhere('phone_number', 'like', "%{$query}%");
            })
            ->whereHas('checkins.minorProcedures.supplies', function ($q) use ($dateConstraint) {
                $q->where('dispensed', false);
                if ($dateConstraint) {
                    $q->where('created_at', '>=', $dateConstraint);
                }
            })
            ->with([
                'checkins' => function ($q) use ($dateConstraint) {
                    $q->whereHas('minorProcedures.supplies', function ($q) use ($dateConstraint) {
                        $q->where('dispensed', false);
                        if ($dateConstraint) {
                            $q->where('created_at', '>=', $dateConstraint);
                        }
                    })
                        ->with(['minorProcedures' => function ($q) use ($dateConstraint) {
                            $q->with([
                                'procedureType:id,name,code',
                                'supplies' => function ($q) use ($dateConstraint) {
                                    $q->where('dispensed', false)
                                        ->with('drug:id,name,form,unit_type');
                                    if ($dateConstraint) {
                                        $q->where('created_at', '>=', $dateConstraint);
                                    }
                                },
                            ]);
                        }])
                        ->latest();
                },
            ])
            ->limit(10)
            ->get()
            ->map(function ($patient) {
                // Collect all procedures with pending supplies
                $procedures = collect();

                foreach ($patient->checkins as $checkin) {
                    foreach ($checkin->minorProcedures as $procedure) {
                        $pendingSupplies = $procedure->supplies->where('dispensed', false);
                        if ($pendingSupplies->isNotEmpty()) {
                            $procedures->push([
                                'procedure_type' => $procedure->procedureType->name,
                                'performed_at' => $procedure->performed_at,
                                'supplies' => $pendingSupplies,
                                'supply_count' => $pendingSupplies->count(),
                            ]);
                        }
                    }
                }

                // Sort procedures by date (most recent first)
                $procedures = $procedures->sortByDesc('performed_at')->values();

                $totalSupplies = $procedures->sum('supply_count');
                $latestProcedure = $procedures->first();

                return [
                    'id' => $patient->id,
                    'patient_number' => $patient->patient_number,
                    'full_name' => $patient->full_name,
                    'phone_number' => $patient->phone_number,
                    'supply_status' => 'pending',
                    'total_supplies' => $totalSupplies,
                    'procedure_count' => $procedures->count(),
                    'last_procedure' => $latestProcedure ? $latestProcedure['performed_at']->diffForHumans() : null,
                    'last_procedure_date' => $latestProcedure ? $latestProcedure['performed_at']->format('Y-m-d') : null,
                    'procedures' => $procedures->map(fn ($proc) => [
                        'procedure_type' => $proc['procedure_type'],
                        'date' => $proc['performed_at']->format('Y-m-d H:i:s'),
                        'date_formatted' => $proc['performed_at']->format('M d, Y'),
                        'date_relative' => $proc['performed_at']->diffForHumans(),
                        'is_today' => $proc['performed_at']->isToday(),
                        'supply_count' => $proc['supply_count'],
                    ])->toArray(),
                ];
            })
            ->filter(fn ($patient) => $patient['total_supplies'] > 0);

        return response()->json($patients->values());
    }

    /**
     * Get supplies for dispensing.
     */
    public function getSuppliesForDispensing(Patient $patient, Request $request)
    {
        $this->authorize('viewAny', Dispensing::class);

        $dateFilter = $request->input('date_filter', 'today');

        // Determine date range based on filter
        $dateConstraint = match ($dateFilter) {
            'today' => now()->startOfDay(),
            'week' => now()->subDays(7)->startOfDay(),
            default => null, // 'all' - no date constraint
        };

        $supplies = MinorProcedureSupply::whereHas('minorProcedure.patientCheckin', function ($q) use ($patient) {
            $q->where('patient_id', $patient->id);
        })
            ->where('dispensed', false)
            ->when($dateConstraint, fn ($q) => $q->where('created_at', '>=', $dateConstraint))
            ->with([
                'drug:id,name,form,unit_type,unit_price',
                'minorProcedure.procedureType:id,name,code',
                'minorProcedure.patientCheckin:id,patient_id,checked_in_at',
            ])
            ->get()
            ->map(function ($supply) {
                return [
                    'supply' => $supply,
                    'procedure_type' => $supply->minorProcedure->procedureType->name,
                    'performed_at' => $supply->minorProcedure->performed_at,
                ];
            });

        return response()->json(['supplies' => $supplies]);
    }

    /**
     * Dispense a minor procedure supply.
     */
    public function dispenseSupply(MinorProcedureSupply $supply): RedirectResponse
    {
        $this->authorize('create', Dispensing::class);

        try {
            $this->dispensingService->dispenseMinorProcedureSupply($supply, auth()->user());

            return redirect()
                ->route('pharmacy.dispensing.index')
                ->with('success', 'Supply dispensed successfully.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()]);
        }
    }
}
