<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\DispensePrescriptionRequest;
use App\Http\Requests\ReviewPrescriptionRequest;
use App\Models\Dispensing;
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

        $pendingCount = Prescription::where('status', 'prescribed')->count();

        return Inertia::render('Pharmacy/Dispensing/Index', [
            'pendingCount' => $pendingCount,
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

        $patients = Patient::query()
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('patient_number', 'like', "%{$query}%")
                    ->orWhere('phone_number', 'like', "%{$query}%");
            })
            ->where(function ($q) use ($dateConstraint) {
                // Has prescriptions from consultations OR ward rounds with any relevant status
                $q->whereHas('checkins.consultations.prescriptions', function ($q) use ($dateConstraint) {
                    $q->whereIn('status', ['prescribed', 'reviewed', 'dispensed']);
                    if ($dateConstraint) {
                        $q->where('created_at', '>=', $dateConstraint);
                    }
                })->orWhereHas('admissions.wardRounds.prescriptions', function ($q) use ($dateConstraint) {
                    $q->whereIn('status', ['prescribed', 'reviewed', 'dispensed']);
                    if ($dateConstraint) {
                        $q->where('created_at', '>=', $dateConstraint);
                    }
                });
            })
            ->with([
                'checkins' => function ($q) use ($dateConstraint) {
                    if ($dateConstraint) {
                        $q->where('created_at', '>=', $dateConstraint);
                    }
                    $q->latest()
                        ->with(['consultations' => function ($q) use ($dateConstraint) {
                            $q->latest()
                                ->with(['prescriptions' => function ($q) use ($dateConstraint) {
                                    $q->whereIn('status', ['prescribed', 'reviewed', 'dispensed'])
                                        ->with('drug:id,name,form,unit_type');
                                    if ($dateConstraint) {
                                        $q->where('created_at', '>=', $dateConstraint);
                                    }
                                }]);
                        }]);
                },
                'admissions' => function ($q) use ($dateConstraint) {
                    $q->whereNull('discharged_at')
                        ->with(['wardRounds' => function ($q) use ($dateConstraint) {
                            if ($dateConstraint) {
                                $q->where('created_at', '>=', $dateConstraint);
                            }
                            $q->with(['prescriptions' => function ($q) use ($dateConstraint) {
                                $q->whereIn('status', ['prescribed', 'reviewed', 'dispensed'])
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
                // Collect all consultations and ward rounds with prescriptions
                $visits = collect();

                // Add consultations from checkins
                foreach ($patient->checkins as $checkin) {
                    foreach ($checkin->consultations as $consultation) {
                        $prescriptions = $consultation->prescriptions;
                        if ($prescriptions->isNotEmpty()) {
                            $visits->push([
                                'type' => 'consultation',
                                'date' => $consultation->started_at ?? $checkin->created_at,
                                'prescriptions' => $prescriptions,
                            ]);
                        }
                    }
                }

                // Add ward rounds from admissions
                foreach ($patient->admissions as $admission) {
                    foreach ($admission->wardRounds as $wardRound) {
                        $prescriptions = $wardRound->prescriptions;
                        if ($prescriptions->isNotEmpty()) {
                            $visits->push([
                                'type' => 'ward_round',
                                'date' => $wardRound->created_at,
                                'prescriptions' => $prescriptions,
                            ]);
                        }
                    }
                }

                // Sort visits by date (most recent first)
                $visits = $visits->sortByDesc('date')->values();

                // Collect all prescriptions
                $allPrescriptions = $visits->flatMap(fn ($visit) => $visit['prescriptions']);

                // Determine the patient's status based on prescriptions
                $prescribedCount = $allPrescriptions->where('status', 'prescribed')->count();
                $reviewedCount = $allPrescriptions->where('status', 'reviewed')->count();
                $dispensedCount = $allPrescriptions->where('status', 'dispensed')->count();

                // Set primary status for the patient
                $status = 'completed'; // default
                if ($prescribedCount > 0) {
                    $status = 'needs_review';
                } elseif ($reviewedCount > 0) {
                    $status = 'ready_to_dispense';
                } elseif ($dispensedCount > 0 && $dispensedCount === $allPrescriptions->count()) {
                    $status = 'completed';
                }

                $latestVisit = $visits->first();

                return [
                    'id' => $patient->id,
                    'patient_number' => $patient->patient_number,
                    'full_name' => $patient->full_name,
                    'phone_number' => $patient->phone_number,
                    'prescription_status' => $status,
                    'prescribed_count' => $prescribedCount,
                    'reviewed_count' => $reviewedCount,
                    'dispensed_count' => $dispensedCount,
                    'total_prescriptions' => $allPrescriptions->count(),
                    'last_visit' => $latestVisit ? $latestVisit['date']->diffForHumans() : null,
                    'last_visit_date' => $latestVisit ? $latestVisit['date']->format('Y-m-d') : null,
                    'visit_count' => $visits->count(),
                    'visits' => $visits->map(fn ($visit) => [
                        'type' => $visit['type'],
                        'date' => $visit['date']->format('Y-m-d H:i:s'),
                        'date_formatted' => $visit['date']->format('M d, Y'),
                        'date_relative' => $visit['date']->diffForHumans(),
                        'is_today' => $visit['date']->isToday(),
                        'prescription_count' => $visit['prescriptions']->count(),
                        'prescribed_count' => $visit['prescriptions']->where('status', 'prescribed')->count(),
                        'reviewed_count' => $visit['prescriptions']->where('status', 'reviewed')->count(),
                        'dispensed_count' => $visit['prescriptions']->where('status', 'dispensed')->count(),
                    ])->toArray(),
                ];
            })
            ->filter(fn ($patient) => $patient['total_prescriptions'] > 0); // Only include patients with prescriptions

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

        $prescriptionsData = $this->dispensingService->getPrescriptionsForReview($patient->id, $dateConstraint);

        return response()->json(['prescriptionsData' => $prescriptionsData]);
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

        $prescriptionsData = $this->dispensingService->getPrescriptionsForDispensing($patient->id, $dateConstraint);

        return response()->json($prescriptionsData);
    }

    /**
     * Touchpoint 1: Process review changes (from modal).
     */
    public function updateReview(ReviewPrescriptionRequest $request, Patient $patient): RedirectResponse
    {
        $this->authorize('viewAny', Dispensing::class);

        $validated = $request->validated();

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

        return redirect()
            ->route('pharmacy.dispensing.index')
            ->with('success', 'Prescriptions reviewed successfully. Patient can now proceed to payment and dispensing.');
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
}
