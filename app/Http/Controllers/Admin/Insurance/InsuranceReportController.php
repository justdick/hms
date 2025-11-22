<?php

namespace App\Http\Controllers\Admin\Insurance;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\InsuranceClaim;
use App\Models\InsuranceClaimItem;
use App\Models\InsuranceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InsuranceReportController extends Controller
{
    public function index(): Response
    {
        abort_unless(auth()->user()->can('insurance.view-reports') || auth()->user()->can('system.admin'), 403);

        return Inertia::render('Admin/Insurance/Reports/Index');
    }

    public function claimsSummary(Request $request)
    {
        abort_unless(auth()->user()->can('insurance.view-reports') || auth()->user()->can('system.admin'), 403);

        $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->endOfMonth()->toDateString());
        $providerId = $request->get('provider_id');

        $cacheKey = "claims_summary_{$dateFrom}_{$dateTo}_{$providerId}";

        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($dateFrom, $dateTo, $providerId) {
            $query = InsuranceClaim::whereBetween('date_of_attendance', [$dateFrom, $dateTo]);

            if ($providerId) {
                $query->whereHas('patientInsurance.plan', function ($q) use ($providerId) {
                    $q->where('insurance_provider_id', $providerId);
                });
            }

            // Get counts by status
            $statusCounts = (clone $query)
                ->select('status', DB::raw('count(*) as count'), DB::raw('sum(total_claim_amount) as total_amount'))
                ->groupBy('status')
                ->get()
                ->keyBy('status');

            // Get total metrics
            $totalClaims = $query->count();
            $totalClaimedAmount = $query->sum('total_claim_amount');
            $totalApprovedAmount = $query->whereIn('status', ['approved', 'paid', 'partial'])
                ->sum('approved_amount');
            $totalPaidAmount = $query->whereIn('status', ['paid', 'partial'])
                ->sum('payment_amount');

            // Get claims by provider
            $claimsByProvider = InsuranceClaim::query()
                ->whereBetween('date_of_attendance', [$dateFrom, $dateTo])
                ->when($providerId, function ($q) use ($providerId) {
                    $q->whereHas('patientInsurance.plan', function ($query) use ($providerId) {
                        $query->where('insurance_provider_id', $providerId);
                    });
                })
                ->join('patient_insurance', 'insurance_claims.patient_insurance_id', '=', 'patient_insurance.id')
                ->join('insurance_plans', 'patient_insurance.insurance_plan_id', '=', 'insurance_plans.id')
                ->join('insurance_providers', 'insurance_plans.insurance_provider_id', '=', 'insurance_providers.id')
                ->select(
                    'insurance_providers.id',
                    'insurance_providers.name',
                    DB::raw('count(insurance_claims.id) as claim_count'),
                    DB::raw('sum(insurance_claims.total_claim_amount) as total_claimed'),
                    DB::raw('sum(insurance_claims.approved_amount) as total_approved'),
                    DB::raw('sum(insurance_claims.payment_amount) as total_paid')
                )
                ->groupBy('insurance_providers.id', 'insurance_providers.name')
                ->get();

            return [
                'total_claims' => $totalClaims,
                'total_claimed_amount' => round($totalClaimedAmount, 2),
                'total_approved_amount' => round($totalApprovedAmount, 2),
                'total_paid_amount' => round($totalPaidAmount, 2),
                'outstanding_amount' => round($totalApprovedAmount - $totalPaidAmount, 2),
                'status_breakdown' => $statusCounts,
                'claims_by_provider' => $claimsByProvider,
            ];
        });

        // Support JSON responses for API calls
        if ($request->wantsJson()) {
            return response()->json(['data' => $data]);
        }

        $providers = InsuranceProvider::orderBy('name')->get();

        return Inertia::render('Admin/Insurance/Reports/ClaimsSummary', [
            'data' => $data,
            'providers' => $providers,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'provider_id' => $providerId,
            ],
        ]);
    }

    public function revenueAnalysis(Request $request)
    {
        abort_unless(auth()->user()->can('insurance.view-reports') || auth()->user()->can('system.admin'), 403);

        $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->endOfMonth()->toDateString());

        $cacheKey = "revenue_analysis_{$dateFrom}_{$dateTo}";

        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($dateFrom, $dateTo) {
            // Insurance revenue
            $insuranceRevenue = InsuranceClaim::whereBetween('date_of_attendance', [$dateFrom, $dateTo])
                ->whereIn('status', ['paid', 'partial'])
                ->sum('payment_amount');

            // Cash revenue (charges not associated with insurance claims)
            $cashRevenue = Charge::whereBetween('created_at', [$dateFrom, $dateTo])
                ->withoutInsurance()
                ->where('status', 'paid')
                ->sum('amount');

            // Insurance claims breakdown by status
            $insuranceByStatus = InsuranceClaim::whereBetween('date_of_attendance', [$dateFrom, $dateTo])
                ->select('status', DB::raw('sum(payment_amount) as total_paid'))
                ->groupBy('status')
                ->get()
                ->keyBy('status');

            // Monthly trend for the last 6 months
            $monthlyTrend = [];
            for ($i = 5; $i >= 0; $i--) {
                $monthStart = now()->subMonths($i)->startOfMonth();
                $monthEnd = now()->subMonths($i)->endOfMonth();

                $insuranceAmount = InsuranceClaim::whereBetween('date_of_attendance', [$monthStart, $monthEnd])
                    ->whereIn('status', ['paid', 'partial'])
                    ->sum('payment_amount');

                $cashAmount = Charge::whereBetween('created_at', [$monthStart, $monthEnd])
                    ->withoutInsurance()
                    ->where('status', 'paid')
                    ->sum('amount');

                $monthlyTrend[] = [
                    'month' => $monthStart->format('M Y'),
                    'insurance' => round($insuranceAmount, 2),
                    'cash' => round($cashAmount, 2),
                    'total' => round($insuranceAmount + $cashAmount, 2),
                ];
            }

            return [
                'insurance_revenue' => round($insuranceRevenue, 2),
                'cash_revenue' => round($cashRevenue, 2),
                'total_revenue' => round($insuranceRevenue + $cashRevenue, 2),
                'insurance_percentage' => $insuranceRevenue + $cashRevenue > 0
                    ? round(($insuranceRevenue / ($insuranceRevenue + $cashRevenue)) * 100, 2)
                    : 0,
                'cash_percentage' => $insuranceRevenue + $cashRevenue > 0
                    ? round(($cashRevenue / ($insuranceRevenue + $cashRevenue)) * 100, 2)
                    : 0,
                'monthly_trend' => $monthlyTrend,
                'insurance_by_status' => $insuranceByStatus,
            ];
        });

        // Support JSON responses for API calls
        if ($request->wantsJson()) {
            return response()->json(['data' => $data]);
        }

        return Inertia::render('Admin/Insurance/Reports/RevenueAnalysis', [
            'data' => $data,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function outstandingClaims(Request $request)
    {
        abort_unless(auth()->user()->can('insurance.view-reports') || auth()->user()->can('system.admin'), 403);

        $providerId = $request->get('provider_id');

        $cacheKey = "outstanding_claims_{$providerId}";

        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($providerId) {
            $query = InsuranceClaim::whereIn('status', ['submitted', 'approved'])
                ->with(['patientInsurance.plan.provider']);

            if ($providerId) {
                $query->whereHas('patientInsurance.plan', function ($q) use ($providerId) {
                    $q->where('insurance_provider_id', $providerId);
                });
            }

            $claims = $query->get();

            // Calculate aging buckets
            $now = now();
            $aging = [
                '0-30' => ['count' => 0, 'amount' => 0],
                '31-60' => ['count' => 0, 'amount' => 0],
                '61-90' => ['count' => 0, 'amount' => 0],
                '90+' => ['count' => 0, 'amount' => 0],
            ];

            $byProvider = [];

            foreach ($claims as $claim) {
                $daysOutstanding = $now->diffInDays($claim->submitted_at ?? $claim->created_at);
                $outstandingAmount = ($claim->approved_amount ?? $claim->total_claim_amount) - ($claim->payment_amount ?? 0);

                // Aging buckets
                if ($daysOutstanding <= 30) {
                    $aging['0-30']['count']++;
                    $aging['0-30']['amount'] += $outstandingAmount;
                } elseif ($daysOutstanding <= 60) {
                    $aging['31-60']['count']++;
                    $aging['31-60']['amount'] += $outstandingAmount;
                } elseif ($daysOutstanding <= 90) {
                    $aging['61-90']['count']++;
                    $aging['61-90']['amount'] += $outstandingAmount;
                } else {
                    $aging['90+']['count']++;
                    $aging['90+']['amount'] += $outstandingAmount;
                }

                // By provider
                $providerName = $claim->patientInsurance->plan->provider->name ?? 'Unknown';
                if (! isset($byProvider[$providerName])) {
                    $byProvider[$providerName] = [
                        'count' => 0,
                        'amount' => 0,
                        'oldest_claim_days' => 0,
                    ];
                }
                $byProvider[$providerName]['count']++;
                $byProvider[$providerName]['amount'] += $outstandingAmount;
                $byProvider[$providerName]['oldest_claim_days'] = max(
                    $byProvider[$providerName]['oldest_claim_days'],
                    $daysOutstanding
                );
            }

            // Round amounts
            foreach ($aging as &$bucket) {
                $bucket['amount'] = round($bucket['amount'], 2);
            }

            foreach ($byProvider as &$provider) {
                $provider['amount'] = round($provider['amount'], 2);
            }

            return [
                'total_outstanding' => $claims->sum(function ($claim) {
                    return ($claim->approved_amount ?? $claim->total_claim_amount) - ($claim->payment_amount ?? 0);
                }),
                'total_claims' => $claims->count(),
                'aging_analysis' => $aging,
                'by_provider' => $byProvider,
            ];
        });

        // Support JSON responses for API calls
        if ($request->wantsJson()) {
            return response()->json(['data' => $data]);
        }

        $providers = InsuranceProvider::orderBy('name')->get();

        return Inertia::render('Admin/Insurance/Reports/OutstandingClaims', [
            'data' => $data,
            'providers' => $providers,
            'filters' => [
                'provider_id' => $providerId,
            ],
        ]);
    }

    public function vettingPerformance(Request $request): Response
    {
        abort_unless(auth()->user()->can('insurance.view-reports') || auth()->user()->can('system.admin'), 403);

        $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->endOfMonth()->toDateString());

        $cacheKey = "vetting_performance_{$dateFrom}_{$dateTo}";

        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($dateFrom, $dateTo) {
            // Get vetting officers performance
            $officersPerformance = InsuranceClaim::whereBetween('vetted_at', [$dateFrom, $dateTo])
                ->whereNotNull('vetted_by')
                ->with('vettedBy')
                ->get()
                ->groupBy('vetted_by')
                ->map(function ($claims, $userId) {
                    $user = $claims->first()->vettedBy;

                    // Calculate average turnaround time (from creation to vetting)
                    $totalTurnaroundMinutes = 0;
                    $claimCount = $claims->count();

                    foreach ($claims as $claim) {
                        if ($claim->created_at && $claim->vetted_at) {
                            $totalTurnaroundMinutes += $claim->created_at->diffInMinutes($claim->vetted_at);
                        }
                    }

                    $avgTurnaroundHours = $claimCount > 0 ? round($totalTurnaroundMinutes / $claimCount / 60, 2) : 0;

                    return [
                        'officer_name' => $user->name ?? 'Unknown',
                        'claims_vetted' => $claimCount,
                        'avg_turnaround_hours' => $avgTurnaroundHours,
                        'approved_for_submission' => $claims->whereIn('status', ['vetted', 'submitted', 'approved', 'paid', 'partial'])->count(),
                        'rejected_at_vetting' => $claims->where('status', 'rejected')->count(),
                    ];
                })
                ->values();

            // Overall metrics
            $totalVetted = InsuranceClaim::whereBetween('vetted_at', [$dateFrom, $dateTo])
                ->whereNotNull('vetted_by')
                ->count();

            $avgTurnaroundTime = InsuranceClaim::whereBetween('vetted_at', [$dateFrom, $dateTo])
                ->whereNotNull('vetted_by')
                ->whereNotNull('vetted_at')
                ->get()
                ->avg(function ($claim) {
                    return $claim->created_at->diffInMinutes($claim->vetted_at);
                });

            return [
                'total_claims_vetted' => $totalVetted,
                'avg_turnaround_hours' => round(($avgTurnaroundTime ?? 0) / 60, 2),
                'officers_performance' => $officersPerformance,
            ];
        });

        return Inertia::render('Admin/Insurance/Reports/VettingPerformance', [
            'data' => $data,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function utilizationReport(Request $request): Response
    {
        abort_unless(auth()->user()->can('insurance.view-reports') || auth()->user()->can('system.admin'), 403);

        $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->endOfMonth()->toDateString());
        $providerId = $request->get('provider_id');

        $cacheKey = "utilization_report_{$dateFrom}_{$dateTo}_{$providerId}";

        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($dateFrom, $dateTo, $providerId) {
            // Top services by insurance provider
            $topServicesQuery = InsuranceClaimItem::query()
                ->join('insurance_claims', 'insurance_claim_items.insurance_claim_id', '=', 'insurance_claims.id')
                ->join('charges', 'insurance_claim_items.charge_id', '=', 'charges.id')
                ->whereBetween('insurance_claims.date_of_attendance', [$dateFrom, $dateTo]);

            if ($providerId) {
                $topServicesQuery->whereHas('insuranceClaim.patientInsurance.plan', function ($q) use ($providerId) {
                    $q->where('insurance_provider_id', $providerId);
                });
            }

            $topServices = $topServicesQuery
                ->select(
                    'charges.description',
                    DB::raw('count(*) as count'),
                    DB::raw('sum(insurance_claim_items.subtotal) as total_claimed'),
                    DB::raw('sum(insurance_claim_items.insurance_pays) as total_approved')
                )
                ->groupBy('charges.description')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'service' => $item->description,
                        'count' => $item->count,
                        'total_claimed' => round($item->total_claimed, 2),
                        'total_approved' => round($item->total_approved, 2),
                    ];
                });

            // Coverage utilization by provider
            $providerUtilization = InsuranceClaim::query()
                ->whereBetween('date_of_attendance', [$dateFrom, $dateTo])
                ->when($providerId, function ($q) use ($providerId) {
                    $q->whereHas('patientInsurance.plan', function ($query) use ($providerId) {
                        $query->where('insurance_provider_id', $providerId);
                    });
                })
                ->join('patient_insurance', 'insurance_claims.patient_insurance_id', '=', 'patient_insurance.id')
                ->join('insurance_plans', 'patient_insurance.insurance_plan_id', '=', 'insurance_plans.id')
                ->join('insurance_providers', 'insurance_plans.insurance_provider_id', '=', 'insurance_providers.id')
                ->select(
                    'insurance_providers.name as provider_name',
                    'insurance_plans.plan_name as plan_name',
                    DB::raw('count(insurance_claims.id) as claim_count'),
                    DB::raw('sum(insurance_claims.total_claim_amount) as total_claimed'),
                    DB::raw('avg(insurance_claims.total_claim_amount) as avg_claim_amount')
                )
                ->groupBy('insurance_providers.name', 'insurance_plans.plan_name')
                ->orderByDesc('claim_count')
                ->get()
                ->map(function ($item) {
                    return [
                        'provider' => $item->provider_name,
                        'plan' => $item->plan_name,
                        'claim_count' => $item->claim_count,
                        'total_claimed' => round($item->total_claimed, 2),
                        'avg_claim_amount' => round($item->avg_claim_amount, 2),
                    ];
                });

            return [
                'top_services' => $topServices,
                'provider_utilization' => $providerUtilization,
            ];
        });

        $providers = InsuranceProvider::orderBy('name')->get();

        return Inertia::render('Admin/Insurance/Reports/UtilizationReport', [
            'data' => $data,
            'providers' => $providers,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'provider_id' => $providerId,
            ],
        ]);
    }

    public function rejectionAnalysis(Request $request): Response
    {
        abort_unless(auth()->user()->can('insurance.view-reports') || auth()->user()->can('system.admin'), 403);

        $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->endOfMonth()->toDateString());
        $providerId = $request->get('provider_id');

        $cacheKey = "rejection_analysis_{$dateFrom}_{$dateTo}_{$providerId}";

        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($dateFrom, $dateTo, $providerId) {
            $query = InsuranceClaim::where('status', 'rejected')
                ->whereBetween('updated_at', [$dateFrom, $dateTo]);

            if ($providerId) {
                $query->whereHas('patientInsurance.plan', function ($q) use ($providerId) {
                    $q->where('insurance_provider_id', $providerId);
                });
            }

            $rejectedClaims = $query->get();

            // Rejection reasons breakdown
            $rejectionReasons = $rejectedClaims
                ->groupBy('rejection_reason')
                ->map(function ($claims, $reason) {
                    return [
                        'reason' => $reason ?? 'Not specified',
                        'count' => $claims->count(),
                        'total_amount' => round($claims->sum('total_claim_amount'), 2),
                    ];
                })
                ->sortByDesc('count')
                ->values();

            // Rejections by provider
            $rejectionsByProvider = $rejectedClaims
                ->groupBy(function ($claim) {
                    return $claim->patientInsurance->plan->provider->name ?? 'Unknown';
                })
                ->map(function ($claims, $provider) {
                    return [
                        'provider' => $provider,
                        'count' => $claims->count(),
                        'total_amount' => round($claims->sum('total_claim_amount'), 2),
                    ];
                })
                ->sortByDesc('count')
                ->values();

            // Rejection trends over time (last 6 months)
            $trends = [];
            for ($i = 5; $i >= 0; $i--) {
                $monthStart = now()->subMonths($i)->startOfMonth();
                $monthEnd = now()->subMonths($i)->endOfMonth();

                $monthRejections = InsuranceClaim::where('status', 'rejected')
                    ->whereBetween('updated_at', [$monthStart, $monthEnd])
                    ->when($providerId, function ($q) use ($providerId) {
                        $q->whereHas('patientInsurance.plan', function ($query) use ($providerId) {
                            $query->where('insurance_provider_id', $providerId);
                        });
                    })
                    ->count();

                $monthTotal = InsuranceClaim::whereBetween('created_at', [$monthStart, $monthEnd])
                    ->when($providerId, function ($q) use ($providerId) {
                        $q->whereHas('patientInsurance.plan', function ($query) use ($providerId) {
                            $query->where('insurance_provider_id', $providerId);
                        });
                    })
                    ->count();

                $trends[] = [
                    'month' => $monthStart->format('M Y'),
                    'rejected' => $monthRejections,
                    'total' => $monthTotal,
                    'rejection_rate' => $monthTotal > 0 ? round(($monthRejections / $monthTotal) * 100, 2) : 0,
                ];
            }

            return [
                'total_rejected' => $rejectedClaims->count(),
                'total_rejected_amount' => round($rejectedClaims->sum('total_claim_amount'), 2),
                'rejection_reasons' => $rejectionReasons,
                'rejections_by_provider' => $rejectionsByProvider,
                'rejection_trends' => $trends,
            ];
        });

        $providers = InsuranceProvider::orderBy('name')->get();

        return Inertia::render('Admin/Insurance/Reports/RejectionAnalysis', [
            'data' => $data,
            'providers' => $providers,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'provider_id' => $providerId,
            ],
        ]);
    }
}
