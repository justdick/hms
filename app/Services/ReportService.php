<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\Department;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * Get outstanding balances with aging categories.
     *
     * @param  array  $filters  Optional filters: department_id, has_insurance, min_amount, max_amount
     */
    public function getOutstandingBalances(array $filters = []): Collection
    {
        $query = Charge::query()
            ->whereIn('status', ['pending', 'partial', 'owing'])
            ->whereNotNull('patient_checkin_id')
            ->with([
                'patientCheckin.patient:id,first_name,last_name,patient_number',
                'patientCheckin.patient.activeInsurance.plan.provider:id,name',
                'patientCheckin.department:id,name',
            ]);

        // Apply filters
        if (! empty($filters['department_id'])) {
            $query->whereHas('patientCheckin', function ($q) use ($filters) {
                $q->where('department_id', $filters['department_id']);
            });
        }

        if (isset($filters['has_insurance'])) {
            if ($filters['has_insurance']) {
                $query->where('is_insurance_claim', true);
            } else {
                $query->where(function ($q) {
                    $q->where('is_insurance_claim', false)
                        ->orWhereNull('is_insurance_claim');
                });
            }
        }

        if (! empty($filters['min_amount'])) {
            $query->whereRaw('(amount - COALESCE(paid_amount, 0)) >= ?', [$filters['min_amount']]);
        }

        if (! empty($filters['max_amount'])) {
            $query->whereRaw('(amount - COALESCE(paid_amount, 0)) <= ?', [$filters['max_amount']]);
        }

        $charges = $query->get();

        // Group by patient and calculate aging
        $patientBalances = $charges->groupBy(function ($charge) {
            return $charge->patientCheckin?->patient_id;
        })->map(function ($patientCharges, $patientId) {
            $patient = $patientCharges->first()->patientCheckin?->patient;
            $insurance = $patient?->activeInsurance;

            // Calculate aging buckets
            $aging = $this->calculateAgingBuckets($patientCharges);

            // Get service type breakdown
            $serviceBreakdown = $patientCharges->groupBy('service_type')
                ->map(function ($charges) {
                    return [
                        'count' => $charges->count(),
                        'amount' => $charges->sum(fn ($c) => $c->amount - ($c->paid_amount ?? 0)),
                    ];
                });

            return [
                'patient_id' => $patientId,
                'patient_name' => $patient ? "{$patient->first_name} {$patient->last_name}" : 'Unknown',
                'patient_number' => $patient?->patient_number ?? 'N/A',
                'has_insurance' => $insurance !== null,
                'insurance_provider' => $insurance?->plan?->provider?->name,
                'total_outstanding' => $patientCharges->sum(fn ($c) => $c->amount - ($c->paid_amount ?? 0)),
                'charge_count' => $patientCharges->count(),
                'aging' => $aging,
                'service_breakdown' => $serviceBreakdown,
                'oldest_charge_date' => $patientCharges->min('charged_at'),
                'departments' => $patientCharges->pluck('patientCheckin.department.name')->unique()->filter()->values(),
            ];
        })->filter(fn ($item) => $item['patient_id'] !== null)
            ->sortByDesc('total_outstanding')
            ->values();

        return $patientBalances;
    }

    /**
     * Calculate aging buckets for a collection of charges.
     */
    public function calculateAgingBuckets(Collection $charges): array
    {
        $now = Carbon::now();

        $buckets = [
            'current' => 0,      // 0-30 days
            'days_30' => 0,      // 31-60 days
            'days_60' => 0,      // 61-90 days
            'days_90_plus' => 0, // 90+ days
        ];

        foreach ($charges as $charge) {
            $chargeDate = $charge->charged_at ?? $charge->created_at;
            $daysOld = $chargeDate ? $now->diffInDays($chargeDate) : 0;
            $outstandingAmount = $charge->amount - ($charge->paid_amount ?? 0);

            $bucket = $this->getAgingBucket($daysOld);
            $buckets[$bucket] += $outstandingAmount;
        }

        return $buckets;
    }

    /**
     * Get the aging bucket name for a given number of days.
     */
    public function getAgingBucket(int $days): string
    {
        if ($days <= 30) {
            return 'current';
        } elseif ($days <= 60) {
            return 'days_30';
        } elseif ($days <= 90) {
            return 'days_60';
        } else {
            return 'days_90_plus';
        }
    }

    /**
     * Get summary statistics for outstanding balances.
     */
    public function getOutstandingSummary(array $filters = []): array
    {
        $balances = $this->getOutstandingBalances($filters);

        $totalOutstanding = $balances->sum('total_outstanding');
        $agingTotals = [
            'current' => $balances->sum('aging.current'),
            'days_30' => $balances->sum('aging.days_30'),
            'days_60' => $balances->sum('aging.days_60'),
            'days_90_plus' => $balances->sum('aging.days_90_plus'),
        ];

        return [
            'total_outstanding' => $totalOutstanding,
            'patient_count' => $balances->count(),
            'charge_count' => $balances->sum('charge_count'),
            'aging_totals' => $agingTotals,
            'insured_count' => $balances->where('has_insurance', true)->count(),
            'uninsured_count' => $balances->where('has_insurance', false)->count(),
        ];
    }

    /**
     * Get departments for filter dropdown.
     */
    public function getDepartments(): Collection
    {
        return Department::active()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Export outstanding balances to array format for Excel/PDF.
     */
    public function exportOutstandingBalances(array $filters = []): array
    {
        $balances = $this->getOutstandingBalances($filters);

        return $balances->map(function ($balance) {
            return [
                'Patient Number' => $balance['patient_number'],
                'Patient Name' => $balance['patient_name'],
                'Insurance' => $balance['has_insurance'] ? $balance['insurance_provider'] : 'None',
                'Total Outstanding' => number_format($balance['total_outstanding'], 2),
                'Current (0-30 days)' => number_format($balance['aging']['current'], 2),
                '31-60 days' => number_format($balance['aging']['days_30'], 2),
                '61-90 days' => number_format($balance['aging']['days_60'], 2),
                '90+ days' => number_format($balance['aging']['days_90_plus'], 2),
                'Departments' => $balance['departments']->implode(', '),
            ];
        })->toArray();
    }

    /**
     * Get revenue report with grouping options.
     *
     * @param  Carbon  $startDate  Start of the reporting period
     * @param  Carbon  $endDate  End of the reporting period
     * @param  string  $groupBy  Grouping option: date, department, service_type, payment_method, cashier
     */
    public function getRevenueReport(Carbon $startDate, Carbon $endDate, string $groupBy = 'date'): array
    {
        $query = Charge::query()
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->with([
                'patientCheckin.department:id,name',
                'processedByUser:id,name',
            ]);

        $charges = $query->get();

        // Group the data based on the groupBy parameter
        $grouped = $this->groupRevenueData($charges, $groupBy);

        // Calculate totals and statistics
        $totalRevenue = $charges->sum('paid_amount');
        $transactionCount = $charges->count();
        $averageTransaction = $transactionCount > 0 ? $totalRevenue / $transactionCount : 0;

        // Get previous period for comparison
        $periodDays = $startDate->diffInDays($endDate) + 1;
        $previousStart = $startDate->copy()->subDays($periodDays);
        $previousEnd = $startDate->copy()->subDay();

        $previousPeriodData = $this->getPreviousPeriodRevenue($previousStart, $previousEnd);

        // Calculate percentage change
        $percentageChange = $previousPeriodData['total'] > 0
            ? (($totalRevenue - $previousPeriodData['total']) / $previousPeriodData['total']) * 100
            : ($totalRevenue > 0 ? 100 : 0);

        return [
            'grouped_data' => $grouped,
            'summary' => [
                'total_revenue' => $totalRevenue,
                'transaction_count' => $transactionCount,
                'average_transaction' => round($averageTransaction, 2),
                'period_start' => $startDate->toDateString(),
                'period_end' => $endDate->toDateString(),
            ],
            'comparison' => [
                'previous_total' => $previousPeriodData['total'],
                'previous_count' => $previousPeriodData['count'],
                'percentage_change' => round($percentageChange, 2),
                'previous_period_start' => $previousStart->toDateString(),
                'previous_period_end' => $previousEnd->toDateString(),
            ],
            'daily_trend' => $this->getDailyTrend($charges, $startDate, $endDate),
        ];
    }

    /**
     * Group revenue data by the specified dimension.
     */
    private function groupRevenueData(Collection $charges, string $groupBy): Collection
    {
        return match ($groupBy) {
            'date' => $this->groupByDate($charges),
            'department' => $this->groupByDepartment($charges),
            'service_type' => $this->groupByServiceType($charges),
            'payment_method' => $this->groupByPaymentMethod($charges),
            'cashier' => $this->groupByCashier($charges),
            default => $this->groupByDate($charges),
        };
    }

    /**
     * Group charges by date.
     */
    private function groupByDate(Collection $charges): Collection
    {
        return $charges->groupBy(function ($charge) {
            return $charge->paid_at->format('Y-m-d');
        })->map(function ($dayCharges, $date) {
            return [
                'key' => $date,
                'label' => Carbon::parse($date)->format('M d, Y'),
                'total' => $dayCharges->sum('paid_amount'),
                'count' => $dayCharges->count(),
                'average' => $dayCharges->count() > 0 ? round($dayCharges->sum('paid_amount') / $dayCharges->count(), 2) : 0,
            ];
        })->sortKeys()->values();
    }

    /**
     * Group charges by department.
     */
    private function groupByDepartment(Collection $charges): Collection
    {
        return $charges->groupBy(function ($charge) {
            return $charge->patientCheckin?->department_id ?? 0;
        })->map(function ($deptCharges, $deptId) {
            $department = $deptCharges->first()->patientCheckin?->department;

            return [
                'key' => $deptId,
                'label' => $department?->name ?? 'Unknown',
                'total' => $deptCharges->sum('paid_amount'),
                'count' => $deptCharges->count(),
                'average' => $deptCharges->count() > 0 ? round($deptCharges->sum('paid_amount') / $deptCharges->count(), 2) : 0,
            ];
        })->sortByDesc('total')->values();
    }

    /**
     * Group charges by service type.
     */
    private function groupByServiceType(Collection $charges): Collection
    {
        return $charges->groupBy('service_type')->map(function ($typeCharges, $type) {
            return [
                'key' => $type,
                'label' => ucfirst(str_replace('_', ' ', $type)),
                'total' => $typeCharges->sum('paid_amount'),
                'count' => $typeCharges->count(),
                'average' => $typeCharges->count() > 0 ? round($typeCharges->sum('paid_amount') / $typeCharges->count(), 2) : 0,
            ];
        })->sortByDesc('total')->values();
    }

    /**
     * Group charges by payment method (from metadata).
     */
    private function groupByPaymentMethod(Collection $charges): Collection
    {
        return $charges->groupBy(function ($charge) {
            return $charge->metadata['payment_method'] ?? 'Unknown';
        })->map(function ($methodCharges, $method) {
            return [
                'key' => $method,
                'label' => ucfirst(str_replace('_', ' ', $method)),
                'total' => $methodCharges->sum('paid_amount'),
                'count' => $methodCharges->count(),
                'average' => $methodCharges->count() > 0 ? round($methodCharges->sum('paid_amount') / $methodCharges->count(), 2) : 0,
            ];
        })->sortByDesc('total')->values();
    }

    /**
     * Group charges by cashier.
     */
    private function groupByCashier(Collection $charges): Collection
    {
        return $charges->groupBy('processed_by')->map(function ($cashierCharges, $cashierId) {
            $cashier = $cashierCharges->first()->processedByUser;

            return [
                'key' => $cashierId ?? 0,
                'label' => $cashier?->name ?? 'Unknown',
                'total' => $cashierCharges->sum('paid_amount'),
                'count' => $cashierCharges->count(),
                'average' => $cashierCharges->count() > 0 ? round($cashierCharges->sum('paid_amount') / $cashierCharges->count(), 2) : 0,
            ];
        })->sortByDesc('total')->values();
    }

    /**
     * Get previous period revenue for comparison.
     */
    private function getPreviousPeriodRevenue(Carbon $startDate, Carbon $endDate): array
    {
        $charges = Charge::query()
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->get();

        return [
            'total' => $charges->sum('paid_amount'),
            'count' => $charges->count(),
        ];
    }

    /**
     * Get daily revenue trend for chart visualization.
     */
    private function getDailyTrend(Collection $charges, Carbon $startDate, Carbon $endDate): array
    {
        // Create a date range with all days
        $period = new \DatePeriod(
            $startDate->copy()->startOfDay(),
            new \DateInterval('P1D'),
            $endDate->copy()->endOfDay()
        );

        $dailyData = [];
        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $dailyData[$dateStr] = [
                'date' => $dateStr,
                'label' => $date->format('M d'),
                'total' => 0,
                'count' => 0,
            ];
        }

        // Fill in actual data
        foreach ($charges as $charge) {
            $dateStr = $charge->paid_at->format('Y-m-d');
            if (isset($dailyData[$dateStr])) {
                $dailyData[$dateStr]['total'] += $charge->paid_amount;
                $dailyData[$dateStr]['count']++;
            }
        }

        return array_values($dailyData);
    }

    /**
     * Export revenue report to array format for Excel/PDF.
     */
    public function exportRevenueReport(Carbon $startDate, Carbon $endDate, string $groupBy = 'date'): array
    {
        $report = $this->getRevenueReport($startDate, $endDate, $groupBy);

        return $report['grouped_data']->map(function ($item) use ($groupBy) {
            $label = match ($groupBy) {
                'date' => 'Date',
                'department' => 'Department',
                'service_type' => 'Service Type',
                'payment_method' => 'Payment Method',
                'cashier' => 'Cashier',
                default => 'Category',
            };

            return [
                $label => $item['label'],
                'Total Revenue' => number_format($item['total'], 2),
                'Transactions' => $item['count'],
                'Average' => number_format($item['average'], 2),
            ];
        })->toArray();
    }
}
