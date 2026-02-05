<?php

namespace App\Http\Controllers\Radiology;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteImagingOrderRequest;
use App\Models\LabOrder;
use App\Models\LabService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RadiologyController extends Controller
{
    /**
     * Display the radiology worklist.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewWorklist-radiology');

        $status = $request->query('status', 'pending');
        $priority = $request->query('priority');
        $modality = $request->query('modality');
        $search = $request->query('search');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $datePreset = $request->query('date_preset');
        $perPage = $request->query('per_page', 20);

        // Default to 'today' filter when no date parameters are provided
        if (!$dateFrom && !$dateTo && !$datePreset) {
            $datePreset = 'today';
            $dateFrom = now()->toDateString();
            $dateTo = now()->toDateString();
        }

        // Build base query for imaging orders only
        $query = LabOrder::query()
            ->imaging()
            ->excludeExternalReferral()
            ->with([
                'labService:id,name,code,modality,category',
                'orderedBy:id,name',
                'imagingAttachments',
                'orderable' => function ($morphTo) {
                    $morphTo->morphWith([
                        \App\Models\Consultation::class => ['patientCheckin.patient:id,patient_number,first_name,last_name,date_of_birth,gender'],
                        \App\Models\WardRound::class => ['patientAdmission.patient:id,patient_number,first_name,last_name,date_of_birth,gender'],
                    ]);
                },
            ]);

        // Status filter
        if ($status === 'pending') {
            $query->whereIn('status', ['ordered', 'in_progress']);
        } elseif ($status !== 'all') {
            $query->byStatus($status);
        }

        // Priority filter
        if ($priority) {
            $query->byPriority($priority);
        }

        // Modality filter
        if ($modality) {
            $query->whereHas('labService', function ($q) use ($modality) {
                $q->where('modality', $modality);
            });
        }

        // Date range filter
        if ($dateFrom) {
            $query->whereDate('ordered_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('ordered_at', '<=', $dateTo);
        }

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHasMorph('orderable', [\App\Models\Consultation::class], function ($consultation) use ($search) {
                    $consultation->whereHas('patientCheckin.patient', function ($patient) use ($search) {
                        $patient->where('first_name', 'LIKE', "%{$search}%")
                            ->orWhere('last_name', 'LIKE', "%{$search}%")
                            ->orWhere('patient_number', 'LIKE', "%{$search}%");
                    });
                })
                    ->orWhereHasMorph('orderable', [\App\Models\WardRound::class], function ($wardRound) use ($search) {
                        $wardRound->whereHas('patientAdmission.patient', function ($patient) use ($search) {
                            $patient->where('first_name', 'LIKE', "%{$search}%")
                                ->orWhere('last_name', 'LIKE', "%{$search}%")
                                ->orWhere('patient_number', 'LIKE', "%{$search}%");
                        });
                    })
                    ->orWhereHas('labService', function ($serviceQuery) use ($search) {
                        $serviceQuery->search($search);
                    });
            });
        }

        // Sort by priority (STAT > urgent > routine) then by order time (oldest first)
        $query->orderByRaw("CASE 
            WHEN priority = 'stat' THEN 1 
            WHEN priority = 'urgent' THEN 2 
            ELSE 3 
        END ASC")
            ->orderBy('ordered_at', 'asc');

        $orders = $query->paginate($perPage);

        // Transform orders to include patient info
        $orders->getCollection()->transform(function ($order) {
            $patient = null;
            $context = null;

            if ($order->orderable instanceof \App\Models\Consultation) {
                $patient = $order->orderable->patientCheckin?->patient;
                $context = 'Consultation';
            } elseif ($order->orderable instanceof \App\Models\WardRound) {
                $patient = $order->orderable->patientAdmission?->patient;
                $context = 'Ward Round - Day ' . $order->orderable->day_number;
            }

            $order->patient = $patient;
            $order->context = $context;
            $order->has_images = $order->imagingAttachments->count() > 0;

            return $order;
        });

        // Get stats
        $stats = [
            'ordered' => LabOrder::imaging()->excludeExternalReferral()->byStatus('ordered')->count(),
            'in_progress' => LabOrder::imaging()->excludeExternalReferral()->byStatus('in_progress')->count(),
            'completed_today' => LabOrder::imaging()
                ->excludeExternalReferral()
                ->byStatus('completed')
                ->when($dateFrom, fn($q) => $q->whereDate('result_entered_at', '>=', $dateFrom))
                ->when($dateTo, fn($q) => $q->whereDate('result_entered_at', '<=', $dateTo))
                ->when(!$dateFrom && !$dateTo, fn($q) => $q->whereDate('result_entered_at', today()))
                ->count(),
        ];

        // Get available modalities for filter
        $modalities = LabService::imaging()
            ->active()
            ->whereNotNull('modality')
            ->distinct('modality')
            ->pluck('modality')
            ->sort()
            ->values();

        return Inertia::render('Radiology/Index', [
            'orders' => $orders,
            'stats' => $stats,
            'modalities' => $modalities,
            'filters' => [
                'status' => $status,
                'priority' => $priority,
                'modality' => $modality,
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'date_preset' => $datePreset,
            ],
        ]);
    }

    /**
     * Display the imaging order details.
     */
    public function show(LabOrder $labOrder): Response
    {
        Gate::authorize('viewWorklist-radiology');

        // Ensure this is an imaging order
        if (!$labOrder->isImaging()) {
            abort(404, 'This is not an imaging order.');
        }

        $labOrder->load([
            'labService',
            'orderedBy:id,name',
            'imagingAttachments.uploadedBy:id,name',
            'orderable' => function ($morphTo) {
                $morphTo->morphWith([
                    \App\Models\Consultation::class => [
                        'patientCheckin.patient:id,patient_number,first_name,last_name,date_of_birth,gender,phone_number',
                        'patientCheckin.department:id,name',
                        'doctor:id,name',
                    ],
                    \App\Models\WardRound::class => [
                        'patientAdmission.patient:id,patient_number,first_name,last_name,date_of_birth,gender,phone_number',
                        'patientAdmission.ward:id,name,code',
                        'doctor:id,name',
                    ],
                ]);
            },
        ]);

        // Get patient info
        $patient = null;
        $context = null;

        if ($labOrder->orderable instanceof \App\Models\Consultation) {
            $patient = $labOrder->orderable->patientCheckin?->patient;
            $context = [
                'type' => 'consultation',
                'department' => $labOrder->orderable->patientCheckin?->department?->name,
                'doctor' => $labOrder->orderable->doctor?->name,
                'presenting_complaint' => $labOrder->orderable->presenting_complaint,
            ];
        } elseif ($labOrder->orderable instanceof \App\Models\WardRound) {
            $patient = $labOrder->orderable->patientAdmission?->patient;
            $context = [
                'type' => 'ward_round',
                'ward' => $labOrder->orderable->patientAdmission?->ward?->name,
                'doctor' => $labOrder->orderable->doctor?->name,
                'day_number' => $labOrder->orderable->day_number,
            ];
        }

        return Inertia::render('Radiology/Show', [
            'labOrder' => $labOrder,
            'patient' => $patient,
            'context' => $context,
        ]);
    }

    /**
     * Mark an imaging order as in progress.
     */
    public function markInProgress(LabOrder $labOrder): RedirectResponse
    {
        Gate::authorize('uploadImages-radiology', $labOrder);

        // Ensure this is an imaging order
        if (!$labOrder->isImaging()) {
            return back()->with('error', 'This is not an imaging order.');
        }

        if ($labOrder->status !== 'ordered') {
            return back()->with('error', 'Can only start processing for ordered imaging studies.');
        }

        $labOrder->markInProgress();

        return back()->with('success', 'Imaging study marked as in progress.');
    }

    /**
     * Complete an imaging order with report.
     */
    public function complete(CompleteImagingOrderRequest $request, LabOrder $labOrder): RedirectResponse
    {
        // Ensure this is an imaging order
        if (!$labOrder->isImaging()) {
            return back()->with('error', 'This is not an imaging order.');
        }

        if (!in_array($labOrder->status, ['ordered', 'in_progress'])) {
            return back()->with('error', 'Can only complete imaging studies that are ordered or in progress.');
        }

        $labOrder->update([
            'status' => 'completed',
            'result_entered_at' => now(),
            'result_notes' => $request->validated()['result_notes'],
        ]);

        return redirect()
            ->route('radiology.index')
            ->with('success', 'Imaging study completed successfully.');
    }
}
