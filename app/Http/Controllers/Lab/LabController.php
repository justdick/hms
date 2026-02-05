<?php

namespace App\Http\Controllers\Lab;

use App\Http\Controllers\Controller;
use App\Models\LabOrder;
use App\Models\LabService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LabController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status', 'pending');
        $priority = $request->query('priority');
        $category = $request->query('category');
        $search = $request->query('search');
        $perPage = $request->query('per_page', 5);
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $datePreset = $request->query('date_preset');

        // Default to 'today' filter when no date parameters are provided
        if (!$dateFrom && !$dateTo && !$datePreset) {
            $datePreset = 'today';
            $dateFrom = now()->toDateString();
            $dateTo = now()->toDateString();
        }

        // Build base query for filtering
        $baseQuery = LabOrder::query();

        if ($status === 'pending') {
            $baseQuery->whereIn('status', ['ordered', 'sample_collected', 'in_progress']);
        } elseif ($status !== 'all') {
            $baseQuery->byStatus($status);
        }

        if ($priority) {
            $baseQuery->byPriority($priority);
        }

        if ($category) {
            $baseQuery->whereHas('labService', function ($q) use ($category) {
                $q->byCategory($category);
            });
        }

        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
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

        // Date range filter
        if ($dateFrom) {
            $baseQuery->whereDate('ordered_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $baseQuery->whereDate('ordered_at', '<=', $dateTo);
        }

        // Get distinct orderable combinations with pagination at DB level
        $distinctOrderables = (clone $baseQuery)
            ->select('orderable_type', 'orderable_id')
            ->selectRaw('MIN(ordered_at) as first_ordered_at')
            ->selectRaw('MAX(CASE WHEN priority = "stat" THEN 1 WHEN priority = "urgent" THEN 2 ELSE 3 END) as priority_rank')
            ->groupBy('orderable_type', 'orderable_id')
            ->orderBy('priority_rank')
            ->orderByDesc('first_ordered_at')
            ->paginate($perPage);

        // Get the orderable keys for this page
        $orderableKeys = $distinctOrderables->getCollection()->map(function ($item) {
            return $item->orderable_type . '_' . $item->orderable_id;
        })->toArray();

        // Fetch full lab orders only for this page's orderables
        $labOrders = collect();
        if (!empty($orderableKeys)) {
            $labOrders = (clone $baseQuery)
                ->with([
                    'labService:id,name,code,category,sample_type,turnaround_time',
                    'orderedBy:id,name',
                    'orderable' => function ($morphTo) {
                        $morphTo->morphWith([
                            \App\Models\Consultation::class => ['patientCheckin.patient'],
                            \App\Models\WardRound::class => ['patientAdmission.patient'],
                        ]);
                    },
                ])
                ->where(function ($q) use ($distinctOrderables) {
                    foreach ($distinctOrderables->getCollection() as $item) {
                        $q->orWhere(function ($subQ) use ($item) {
                            $subQ->where('orderable_type', $item->orderable_type)
                                ->where('orderable_id', $item->orderable_id);
                        });
                    }
                })
                ->get();
        }

        // Group and transform the orders
        $groupedData = $labOrders->groupBy(function ($order) {
            return $order->orderable_type . '_' . $order->orderable_id;
        })->map(function ($orders) {
            $firstOrder = $orders->first();
            $orderable = $firstOrder->orderable;

            if (!$orderable) {
                return null;
            }

            if ($orderable instanceof \App\Models\Consultation) {
                $patient = $orderable->patientCheckin?->patient;
                $context = $orderable->presenting_complaint;
                $orderableType = 'consultation';
                $orderableId = $orderable->id;
            } else {
                $patient = $orderable->patientAdmission?->patient;
                $context = $orderable->presenting_complaint ?? 'Ward Round - Day ' . $orderable->day_number;
                $orderableType = 'ward_round';
                $orderableId = $orderable->id;
            }

            if (!$patient) {
                return null;
            }

            $statusCounts = $orders->countBy('status');
            $highestPriority = $orders->sortBy(function ($order) {
                return ['stat' => 1, 'urgent' => 2, 'routine' => 3][$order->priority] ?? 3;
            })->first()->priority;

            return [
                'orderable_type' => $orderableType,
                'orderable_id' => $orderableId,
                'patient' => [
                    'id' => $patient->id,
                    'patient_number' => $patient->patient_number,
                    'first_name' => $patient->first_name,
                    'last_name' => $patient->last_name,
                    'phone_number' => $patient->phone_number,
                    'active_insurance' => $patient->activeInsurance?->load('plan.provider'),
                ],
                'patient_number' => $patient->patient_number,
                'context' => $context,
                'ordered_at' => $firstOrder->ordered_at,
                'test_count' => $orders->count(),
                'tests' => $orders->map(fn($order) => [
                    'id' => $order->id,
                    'name' => $order->labService->name,
                    'code' => $order->labService->code,
                    'category' => $order->labService->category,
                    'status' => $order->status,
                    'priority' => $order->priority,
                ])->values(),
                'status_summary' => $statusCounts->toArray(),
                'priority' => $highestPriority,
                'ordered_by' => $firstOrder->orderedBy->name,
            ];
        })->filter()->values();

        // Replace paginator data with transformed grouped data
        $distinctOrderables->setCollection($groupedData);

        $stats = [
            'ordered' => LabOrder::byStatus('ordered')->count(),
            'sample_collected' => LabOrder::byStatus('sample_collected')->count(),
            'in_progress' => LabOrder::byStatus('in_progress')->count(),
            'completed_today' => LabOrder::byStatus('completed')
                ->when($dateFrom, fn($q) => $q->whereDate('result_entered_at', '>=', $dateFrom))
                ->when($dateTo, fn($q) => $q->whereDate('result_entered_at', '<=', $dateTo))
                ->when(!$dateFrom && !$dateTo, fn($q) => $q->whereDate('result_entered_at', today()))
                ->count(),
        ];

        $categories = LabService::active()
            ->distinct('category')
            ->pluck('category')
            ->sort()
            ->values();

        return Inertia::render('Lab/Index', [
            'groupedOrders' => $distinctOrderables,
            'stats' => $stats,
            'categories' => $categories,
            'filters' => [
                'status' => $status,
                'priority' => $priority,
                'category' => $category,
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'date_preset' => $datePreset,
            ],
        ]);
    }

    public function showConsultation(\App\Models\Consultation $consultation): Response
    {
        $consultation->load([
            'patientCheckin.patient:id,patient_number,first_name,last_name,phone_number,date_of_birth,gender',
            'patientCheckin.patient.activeInsurance.plan.provider:id,name,code',
            'labOrders' => function ($query) {
                // Don't show cancelled orders by default
                $query->where('status', '!=', 'cancelled')
                    ->with(['labService:id,name,code,price,test_parameters,category', 'orderedBy:id,name']);
            },
        ]);

        // Get all lab orders for this consultation
        $labOrders = $consultation->labOrders;

        // Get hospital info for printing from theme settings
        $themeService = app(\App\Services\ThemeSettingService::class);
        $theme = $themeService->getTheme();
        $branding = $theme['branding'] ?? [];

        $hospital = [
            'name' => $branding['hospitalName'] ?? 'Hospital Management System',
            'logo_url' => !empty($branding['logoUrl']) ? asset('storage/' . $branding['logoUrl']) : null,
        ];

        return Inertia::render('Lab/ConsultationShow', [
            'consultation' => $consultation,
            'labOrders' => $labOrders,
            'hospital' => $hospital,
        ]);
    }

    public function showWardRound(\App\Models\WardRound $wardRound): Response
    {
        $wardRound->load([
            'patientAdmission.patient:id,patient_number,first_name,last_name,phone_number,date_of_birth,gender',
            'patientAdmission.patient.activeInsurance.plan.provider:id,name,code',
            'patientAdmission.ward:id,name,code',
            'doctor:id,name',
            'labOrders' => function ($query) {
                // Don't show cancelled orders by default
                $query->where('status', '!=', 'cancelled')
                    ->with(['labService:id,name,code,price,test_parameters,category', 'orderedBy:id,name']);
            },
        ]);

        // Get all lab orders for this ward round
        $labOrders = $wardRound->labOrders;

        // Get hospital info for printing from theme settings
        $themeService = app(\App\Services\ThemeSettingService::class);
        $theme = $themeService->getTheme();
        $branding = $theme['branding'] ?? [];

        $hospital = [
            'name' => $branding['hospitalName'] ?? 'Hospital Management System',
            'logo_url' => !empty($branding['logoUrl']) ? asset('storage/' . $branding['logoUrl']) : null,
        ];

        return Inertia::render('Lab/WardRoundShow', [
            'wardRound' => $wardRound,
            'labOrders' => $labOrders,
            'hospital' => $hospital,
        ]);
    }

    public function show(LabOrder $labOrder): Response
    {
        $labOrder->load([
            'orderable' => function ($morphTo) {
                $morphTo->morphWith([
                    \App\Models\Consultation::class => [
                        'patientCheckin.patient:id,patient_number,first_name,last_name,phone_number,date_of_birth,gender',
                    ],
                    \App\Models\WardRound::class => [
                        'patientAdmission.patient:id,patient_number,first_name,last_name,phone_number,date_of_birth,gender',
                    ],
                ]);
            },
            'labService',
            'orderedBy:id,name',
        ]);

        // For backward compatibility, add consultation property if orderable is a Consultation
        if ($labOrder->orderable instanceof \App\Models\Consultation) {
            $labOrder->setRelation('consultation', $labOrder->orderable);
        }

        return Inertia::render('Lab/Show', [
            'labOrder' => $labOrder,
        ]);
    }


    public function collectSample(LabOrder $labOrder): RedirectResponse
    {
        if ($labOrder->status !== 'ordered') {
            return back()->with('error', 'Can only collect samples for ordered tests.');
        }

        $labOrder->markSampleCollected();

        return back()->with('success', 'Sample collected successfully.');
    }

    public function startProcessing(LabOrder $labOrder): RedirectResponse
    {
        if (!in_array($labOrder->status, ['ordered', 'sample_collected'])) {
            return back()->with('error', 'Can only start processing for ordered or sample collected tests.');
        }

        $labOrder->markInProgress();

        return back()->with('success', 'Test processing started.');
    }

    public function complete(Request $request, LabOrder $labOrder): RedirectResponse
    {
        if ($labOrder->status !== 'in_progress') {
            return back()->with('error', 'Can only complete tests that are in progress.');
        }

        $validated = $request->validate([
            'result_values' => 'nullable|array',
            'result_notes' => 'nullable|string|max:2000',
        ]);

        $labOrder->markCompleted(
            $validated['result_values'] ?? null,
            $validated['result_notes'] ?? null
        );

        return back()->with('success', 'Test results entered successfully.');
    }


    public function cancel(Request $request, LabOrder $labOrder): RedirectResponse
    {
        if ($labOrder->status === 'completed') {
            return back()->with('error', 'Cannot cancel completed tests.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $labOrder->update([
            'status' => 'cancelled',
            'result_notes' => 'Cancelled: ' . $validated['reason'],
        ]);

        return back()->with('success', 'Lab order cancelled successfully.');
    }
}
