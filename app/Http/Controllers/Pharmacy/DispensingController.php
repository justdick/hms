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

        if (empty($query)) {
            return response()->json([]);
        }

        $patients = Patient::query()
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('patient_number', 'like', "%{$query}%")
                    ->orWhere('phone_number', 'like', "%{$query}%");
            })
            ->where(function ($q) {
                // Has prescriptions from consultations OR ward rounds
                $q->whereHas('checkins.consultations.prescriptions', function ($q) {
                    $q->where('status', 'prescribed');
                })->orWhereHas('admissions.wardRounds.prescriptions', function ($q) {
                    $q->where('status', 'prescribed');
                });
            })
            ->with([
                'checkins' => function ($q) {
                    $q->latest()
                        ->limit(1)
                        ->with(['consultations' => function ($q) {
                            $q->latest()
                                ->limit(1)
                                ->with(['prescriptions' => function ($q) {
                                    $q->where('status', 'prescribed')
                                        ->with('drug:id,name,form,unit_type');
                                }]);
                        }]);
                },
                'admissions' => function ($q) {
                    $q->whereNull('discharged_at')
                        ->with(['wardRounds.prescriptions' => function ($q) {
                            $q->where('status', 'prescribed')
                                ->with('drug:id,name,form,unit_type');
                        }]);
                },
            ])
            ->limit(10)
            ->get()
            ->map(function ($patient) {
                $latestCheckin = $patient->checkins->first();
                $latestConsultation = $latestCheckin?->consultations->first();
                $consultationPrescriptions = $latestConsultation?->prescriptions ?? collect();

                // Get ward round prescriptions
                $wardRoundPrescriptions = collect();
                $activeAdmission = $patient->admissions->first();
                if ($activeAdmission) {
                    foreach ($activeAdmission->wardRounds as $wardRound) {
                        $wardRoundPrescriptions = $wardRoundPrescriptions->merge($wardRound->prescriptions);
                    }
                }

                $pendingPrescriptions = $consultationPrescriptions->merge($wardRoundPrescriptions);

                return [
                    'id' => $patient->id,
                    'patient_number' => $patient->patient_number,
                    'full_name' => $patient->full_name,
                    'phone_number' => $patient->phone_number,
                    'pending_prescriptions_count' => $pendingPrescriptions->count(),
                    'last_visit' => $latestCheckin?->created_at?->diffForHumans(),
                ];
            });

        return response()->json($patients);
    }

    public function show(Patient $patient): Response
    {
        $this->authorize('viewAny', Dispensing::class);

        // Load latest checkin with consultations and their prescriptions
        $patient->load([
            'checkins' => function ($q) {
                $q->latest()
                    ->limit(1)
                    ->with(['consultations' => function ($q) {
                        $q->latest()
                            ->limit(1)
                            ->with(['prescriptions' => function ($q) {
                                $q->where('status', 'prescribed')
                                    ->with('drug:id,name,form,unit_type,strength');
                            }]);
                    }]);
            },
        ]);

        $latestCheckin = $patient->checkins->first();
        $latestConsultation = $latestCheckin?->consultations->first();

        // Get prescriptions from consultations
        $consultationPrescriptions = $latestConsultation?->prescriptions ?? collect();

        // Get prescriptions from ward rounds for admitted patients
        $wardRoundPrescriptions = collect();
        $activeAdmission = $patient->admissions()
            ->whereNull('discharged_at')
            ->with(['wardRounds.prescriptions' => function ($q) {
                $q->where('status', 'prescribed')
                    ->with('drug:id,name,form,unit_type,strength');
            }])
            ->first();

        if ($activeAdmission) {
            foreach ($activeAdmission->wardRounds as $wardRound) {
                $wardRoundPrescriptions = $wardRoundPrescriptions->merge($wardRound->prescriptions);
            }
        }

        // Combine all prescriptions
        $prescriptions = $consultationPrescriptions->merge($wardRoundPrescriptions);

        // Get prescription data with stock info for review modal
        $prescriptionsData = null;
        if ($latestCheckin && $prescriptions->isNotEmpty()) {
            $prescriptionsData = $this->dispensingService->getPrescriptionsForReview($latestCheckin->id);
        }

        return Inertia::render('Pharmacy/Dispensing/Show', [
            'patient' => $patient,
            'prescriptions' => $prescriptions,
            'prescriptionsData' => $prescriptionsData,
        ]);
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
            ->route('pharmacy.dispensing.dispense.show', $patient)
            ->with('success', 'Prescriptions reviewed successfully. Patient can now proceed to billing.');
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
