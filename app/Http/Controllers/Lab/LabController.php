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

        $query = LabOrder::with([
            'orderable',
            'labService:id,name,code,category,sample_type,turnaround_time',
            'orderedBy:id,name',
        ])
            ->with(['orderable' => function ($morphTo) {
                $morphTo->morphWith([
                    \App\Models\Consultation::class => ['patientCheckin.patient'],
                    \App\Models\WardRound::class => ['patientAdmission.patient'],
                ]);
            }]);

        if ($status === 'pending') {
            // Show all non-completed orders by default
            $query->whereIn('status', ['ordered', 'sample_collected', 'in_progress']);
        } elseif ($status !== 'all') {
            $query->byStatus($status);
        }

        if ($priority) {
            $query->byPriority($priority);
        }

        if ($category) {
            $query->whereHas('labService', function ($q) use ($category) {
                $q->byCategory($category);
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                // Search in patients from consultations
                $q->whereHasMorph('orderable', [\App\Models\Consultation::class], function ($consultation) use ($search) {
                    $consultation->whereHas('patientCheckin.patient', function ($patient) use ($search) {
                        $patient->where('first_name', 'LIKE', "%{$search}%")
                            ->orWhere('last_name', 'LIKE', "%{$search}%")
                            ->orWhere('email', 'LIKE', "%{$search}%");
                    });
                })
                // Search in patients from ward rounds
                    ->orWhereHasMorph('orderable', [\App\Models\WardRound::class], function ($wardRound) use ($search) {
                        $wardRound->whereHas('patientAdmission.patient', function ($patient) use ($search) {
                            $patient->where('first_name', 'LIKE', "%{$search}%")
                                ->orWhere('last_name', 'LIKE', "%{$search}%")
                                ->orWhere('email', 'LIKE', "%{$search}%");
                        });
                    })
                // Search in lab services
                    ->orWhereHas('labService', function ($serviceQuery) use ($search) {
                        $serviceQuery->search($search);
                    });
            });
        }

        // Get all lab orders matching the filters
        $allOrders = $query->orderByDesc('ordered_at')
            ->orderByRaw("CASE WHEN priority = 'stat' THEN 1 WHEN priority = 'urgent' THEN 2 ELSE 3 END")
            ->get();

        // Group lab orders by orderable (consultation or ward round)
        $groupedOrders = $allOrders->groupBy(function ($order) {
            return $order->orderable_type.'_'.$order->orderable_id;
        })->map(function ($orders) {
            $firstOrder = $orders->first();
            $orderable = $firstOrder->orderable;

            // Get patient based on orderable type
            if ($orderable instanceof \App\Models\Consultation) {
                $patient = $orderable->patientCheckin->patient;
                $context = $orderable->presenting_complaint;
                $orderableType = 'consultation';
                $orderableId = $orderable->id;
            } else { // WardRound
                $patient = $orderable->patientAdmission->patient;
                $context = $orderable->presenting_complaint ?? 'Ward Round - Day '.$orderable->day_number;
                $orderableType = 'ward_round';
                $orderableId = $orderable->id;
            }

            // Calculate status summary
            $statusCounts = $orders->countBy('status');
            $highestPriority = $orders->sortBy(function ($order) {
                return ['stat' => 1, 'urgent' => 2, 'routine' => 3][$order->priority];
            })->first()->priority;

            return [
                'orderable_type' => $orderableType,
                'orderable_id' => $orderableId,
                'patient' => $patient,
                'patient_number' => $patient->patient_number,
                'context' => $context,
                'ordered_at' => $firstOrder->ordered_at,
                'test_count' => $orders->count(),
                'tests' => $orders->map(fn ($order) => [
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
        })->values();

        // Paginate the grouped results
        $perPage = 25;
        $currentPage = $request->query('page', 1);
        $total = $groupedOrders->count();

        $paginatedOrders = new \Illuminate\Pagination\LengthAwarePaginator(
            $groupedOrders->forPage($currentPage, $perPage),
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $stats = [
            'ordered' => LabOrder::byStatus('ordered')->count(),
            'sample_collected' => LabOrder::byStatus('sample_collected')->count(),
            'in_progress' => LabOrder::byStatus('in_progress')->count(),
            'completed_today' => LabOrder::byStatus('completed')
                ->whereDate('result_entered_at', today())
                ->count(),
        ];

        $categories = LabService::active()
            ->distinct('category')
            ->pluck('category')
            ->sort()
            ->values();

        return Inertia::render('Lab/Index', [
            'groupedOrders' => $paginatedOrders,
            'stats' => $stats,
            'categories' => $categories,
            'filters' => [
                'status' => $status,
                'priority' => $priority,
                'category' => $category,
                'search' => $search,
            ],
        ]);
    }

    public function showConsultation(\App\Models\Consultation $consultation): Response
    {
        $consultation->load([
            'patientCheckin.patient:id,patient_number,first_name,last_name,phone_number,date_of_birth,gender',
            'labOrders' => function ($query) {
                // Don't show cancelled orders by default
                $query->where('status', '!=', 'cancelled')
                    ->with(['labService', 'orderedBy:id,name']);
            },
        ]);

        // Get all lab orders for this consultation
        $labOrders = $consultation->labOrders;

        return Inertia::render('Lab/ConsultationShow', [
            'consultation' => $consultation,
            'labOrders' => $labOrders,
        ]);
    }

    public function showWardRound(\App\Models\WardRound $wardRound): Response
    {
        $wardRound->load([
            'patientAdmission.patient:id,patient_number,first_name,last_name,phone_number,date_of_birth,gender',
            'patientAdmission.ward:id,name,code',
            'doctor:id,name',
            'labOrders' => function ($query) {
                // Don't show cancelled orders by default
                $query->where('status', '!=', 'cancelled')
                    ->with(['labService', 'orderedBy:id,name']);
            },
        ]);

        // Get all lab orders for this ward round
        $labOrders = $wardRound->labOrders;

        return Inertia::render('Lab/WardRoundShow', [
            'wardRound' => $wardRound,
            'labOrders' => $labOrders,
        ]);
    }

    public function show(LabOrder $labOrder): Response
    {
        $labOrder->load([
            'consultation.patientCheckin.patient:id,patient_number,first_name,last_name,phone_number,date_of_birth,gender',
            'consultation:id,patient_checkin_id,presenting_complaint,history_presenting_complaint,created_at',
            'labService',
            'orderedBy:id,name',
        ]);

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
        if (! in_array($labOrder->status, ['ordered', 'sample_collected'])) {
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

        return redirect()
            ->route('lab.index')
            ->with('success', 'Test results entered successfully.');
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
            'result_notes' => 'Cancelled: '.$validated['reason'],
        ]);

        return back()->with('success', 'Lab order cancelled successfully.');
    }
}
