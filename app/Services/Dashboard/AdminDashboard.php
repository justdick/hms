<?php

namespace App\Services\Dashboard;

use App\Models\Charge;
use App\Models\Department;
use App\Models\PatientCheckin;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard widget for admin metrics and system overview.
 *
 * Provides system-wide metrics including total patients today, total revenue,
 * active users count, and department activity summary for users with admin permissions.
 */
class AdminDashboard extends AbstractDashboardWidget
{
    protected ?Carbon $startDate = null;

    protected ?Carbon $endDate = null;

    protected string $datePreset = 'today';

    /**
     * Get the unique identifier for this widget.
     */
    public function getWidgetId(): string
    {
        return 'admin_metrics';
    }

    /**
     * Get the required permissions to view this widget.
     *
     * @return array<string>
     */
    public function getRequiredPermissions(): array
    {
        return ['system.admin'];
    }

    /**
     * Set the date range for filtering metrics.
     */
    public function setDateRange(?string $startDate, ?string $endDate, string $preset = 'custom'): self
    {
        $this->datePreset = $preset;

        if ($startDate && $endDate) {
            $this->startDate = Carbon::parse($startDate)->startOfDay();
            $this->endDate = Carbon::parse($endDate)->endOfDay();
        } else {
            // Apply preset
            [$this->startDate, $this->endDate] = $this->getPresetDateRange($preset);
        }

        return $this;
    }

    /**
     * Get date range based on preset.
     *
     * @return array{Carbon, Carbon}
     */
    protected function getPresetDateRange(string $preset): array
    {
        return match ($preset) {
            'today' => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()],
            'week' => [Carbon::now()->startOfWeek()->startOfDay(), Carbon::now()->endOfWeek()->endOfDay()],
            'month' => [Carbon::now()->startOfMonth()->startOfDay(), Carbon::now()->endOfMonth()->endOfDay()],
            'year' => [Carbon::now()->startOfYear()->startOfDay(), Carbon::now()->endOfYear()->endOfDay()],
            default => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()],
        };
    }

    /**
     * Get the current date range.
     *
     * @return array{Carbon, Carbon}
     */
    protected function getDateRange(): array
    {
        if ($this->startDate && $this->endDate) {
            return [$this->startDate, $this->endDate];
        }

        return $this->getPresetDateRange('today');
    }

    /**
     * Generate cache key suffix based on date range.
     */
    protected function getDateCacheKey(): string
    {
        [$start, $end] = $this->getDateRange();

        return $start->format('Ymd').'_'.$end->format('Ymd');
    }

    /**
     * Get metrics data for the admin dashboard.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(User $user): array
    {
        $dateCacheKey = $this->getDateCacheKey();

        // Admin metrics are system-wide aggregates (5 min cache)
        return [
            'totalPatientsToday' => $this->cacheSystem("total_patients_{$dateCacheKey}", fn () => $this->getTotalPatients()),
            'totalRevenueToday' => $this->cacheSystem("total_revenue_{$dateCacheKey}", fn () => $this->getTotalRevenue()),
            'activeUsersCount' => $this->cacheSystem('active_users_count', fn () => $this->getActiveUsersCount()),
            'totalDepartments' => $this->cacheSystem('total_departments', fn () => $this->getTotalActiveDepartments()),
            'nhisAttendance' => $this->cacheSystem("nhis_attendance_{$dateCacheKey}", fn () => $this->getNhisAttendance()),
            'nonInsuredAttendance' => $this->cacheSystem("non_insured_attendance_{$dateCacheKey}", fn () => $this->getNonInsuredAttendance()),
        ];
    }

    /**
     * Get list data for the admin dashboard.
     *
     * @return array<string, Collection>
     */
    public function getListData(User $user): array
    {
        $dateCacheKey = $this->getDateCacheKey();

        return [
            'patientFlowTrend' => $this->cacheSystem("patient_flow_trend_{$dateCacheKey}", fn () => $this->getPatientFlowTrend()),
            'revenueTrend' => $this->cacheSystem("revenue_trend_{$dateCacheKey}", fn () => $this->getRevenueTrend()),
            'departmentActivity' => $this->cacheSystem("department_activity_{$dateCacheKey}", fn () => $this->getDepartmentActivity()),
            'attendanceBreakdown' => $this->cacheSystem("attendance_breakdown_{$dateCacheKey}", fn () => $this->getAttendanceBreakdown()),
        ];
    }

    /**
     * Get total patients checked in within date range.
     */
    protected function getTotalPatients(): int
    {
        [$startDate, $endDate] = $this->getDateRange();

        return PatientCheckin::query()
            ->whereBetween('checked_in_at', [$startDate, $endDate])
            ->where(function ($query) {
                $query->where('migrated_from_mittag', false)
                    ->orWhereNull('migrated_from_mittag');
            })
            ->count();
    }

    /**
     * Get total revenue collected within date range.
     */
    protected function getTotalRevenue(): float
    {
        [$startDate, $endDate] = $this->getDateRange();

        return (float) Charge::query()
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->whereIn('status', ['paid', 'partial'])
            ->notVoided()
            ->sum('paid_amount');
    }

    /**
     * Get NHIS (insured) attendance count within date range.
     */
    protected function getNhisAttendance(): int
    {
        [$startDate, $endDate] = $this->getDateRange();

        return PatientCheckin::query()
            ->whereBetween('checked_in_at', [$startDate, $endDate])
            ->where(function ($query) {
                $query->where('migrated_from_mittag', false)
                    ->orWhereNull('migrated_from_mittag');
            })
            ->whereHas('patient.activeInsurance.plan.provider', function ($query) {
                $query->where('is_nhis', true);
            })
            ->count();
    }

    /**
     * Get non-insured (cash) attendance count within date range.
     */
    protected function getNonInsuredAttendance(): int
    {
        [$startDate, $endDate] = $this->getDateRange();

        $totalPatients = $this->getTotalPatients();
        $nhisPatients = $this->getNhisAttendance();

        // Also count patients with non-NHIS insurance as separate category
        $otherInsuredCount = PatientCheckin::query()
            ->whereBetween('checked_in_at', [$startDate, $endDate])
            ->whereHas('patient.activeInsurance.plan.provider', function ($query) {
                $query->where('is_nhis', false);
            })
            ->count();

        // Non-insured = Total - NHIS - Other Insurance
        return $totalPatients - $nhisPatients - $otherInsuredCount;
    }

    /**
     * Get attendance breakdown by insurance type.
     *
     * @return Collection<int, array{type: string, count: int, percentage: float, fill: string}>
     */
    protected function getAttendanceBreakdown(): Collection
    {
        [$startDate, $endDate] = $this->getDateRange();

        $total = PatientCheckin::query()
            ->whereBetween('checked_in_at', [$startDate, $endDate])
            ->where(function ($query) {
                $query->where('migrated_from_mittag', false)
                    ->orWhereNull('migrated_from_mittag');
            })
            ->count();

        if ($total === 0) {
            return collect([
                ['type' => 'NHIS', 'label' => 'NHIS', 'count' => 0, 'percentage' => 0, 'fill' => '#10b981'],
                ['type' => 'other_insurance', 'label' => 'Other Insurance', 'count' => 0, 'percentage' => 0, 'fill' => '#3b82f6'],
                ['type' => 'cash', 'label' => 'Cash/Non-Insured', 'count' => 0, 'percentage' => 0, 'fill' => '#f59e0b'],
            ]);
        }

        // NHIS count
        $nhisCount = PatientCheckin::query()
            ->whereBetween('checked_in_at', [$startDate, $endDate])
            ->where(function ($query) {
                $query->where('migrated_from_mittag', false)
                    ->orWhereNull('migrated_from_mittag');
            })
            ->whereHas('patient.activeInsurance.plan.provider', function ($query) {
                $query->where('is_nhis', true);
            })
            ->count();

        // Other insurance count
        $otherInsuranceCount = PatientCheckin::query()
            ->whereBetween('checked_in_at', [$startDate, $endDate])
            ->where(function ($query) {
                $query->where('migrated_from_mittag', false)
                    ->orWhereNull('migrated_from_mittag');
            })
            ->whereHas('patient.activeInsurance.plan.provider', function ($query) {
                $query->where('is_nhis', false);
            })
            ->count();

        // Cash/Non-insured count
        $cashCount = $total - $nhisCount - $otherInsuranceCount;

        return collect([
            [
                'type' => 'nhis',
                'label' => 'NHIS',
                'count' => $nhisCount,
                'percentage' => round(($nhisCount / $total) * 100, 1),
                'fill' => '#10b981', // emerald
            ],
            [
                'type' => 'other_insurance',
                'label' => 'Other Insurance',
                'count' => $otherInsuranceCount,
                'percentage' => round(($otherInsuranceCount / $total) * 100, 1),
                'fill' => '#3b82f6', // blue
            ],
            [
                'type' => 'cash',
                'label' => 'Cash/Non-Insured',
                'count' => $cashCount,
                'percentage' => round(($cashCount / $total) * 100, 1),
                'fill' => '#f59e0b', // amber
            ],
        ]);
    }

    /**
     * Get count of active users (users who are active in the system).
     * Since we don't track last_activity_at, we count all active users.
     */
    protected function getActiveUsersCount(): int
    {
        return User::query()
            ->active()
            ->count();
    }

    /**
     * Get total number of active departments.
     */
    protected function getTotalActiveDepartments(): int
    {
        return Department::query()
            ->active()
            ->count();
    }

    /**
     * Get patient flow trend based on date range.
     * For single day: shows hourly breakdown
     * For week/month: shows daily breakdown
     * For year: shows monthly breakdown
     *
     * @return Collection<int, array{date: string, day: string, checkins: int, consultations: int}>
     */
    protected function getPatientFlowTrend(): Collection
    {
        [$startDate, $endDate] = $this->getDateRange();
        $daysDiff = $startDate->diffInDays($endDate);

        // For single day, show last 7 days for context
        if ($daysDiff === 0) {
            $startDate = Carbon::today()->subDays(6);
            $endDate = Carbon::today();
        }

        // Get check-ins per day (exclude migrated records)
        $checkins = PatientCheckin::query()
            ->whereBetween('checked_in_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->where(function ($query) {
                $query->where('migrated_from_mittag', false)
                    ->orWhereNull('migrated_from_mittag');
            })
            ->select(
                DB::raw('DATE(checked_in_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // Get consultations per day (exclude migrated records)
        $consultations = DB::table('consultations')
            ->whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->where(function ($query) {
                $query->where('migrated_from_mittag', false)
                    ->orWhereNull('migrated_from_mittag');
            })
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // Build the trend data
        $period = CarbonPeriod::create($startDate, $endDate);

        return collect($period)->map(function (Carbon $date) use ($checkins, $consultations) {
            $dateStr = $date->toDateString();

            return [
                'date' => $dateStr,
                'day' => $date->format('D'),
                'fullDate' => $date->format('M d'),
                'checkins' => $checkins[$dateStr] ?? 0,
                'consultations' => $consultations[$dateStr] ?? 0,
            ];
        })->values();
    }

    /**
     * Get revenue trend based on date range.
     *
     * @return Collection<int, array{date: string, day: string, revenue: float}>
     */
    protected function getRevenueTrend(): Collection
    {
        [$startDate, $endDate] = $this->getDateRange();
        $daysDiff = $startDate->diffInDays($endDate);

        // For single day, show last 7 days for context
        if ($daysDiff === 0) {
            $startDate = Carbon::today()->subDays(6);
            $endDate = Carbon::today();
        }

        // Get revenue per day
        $revenue = Charge::query()
            ->whereBetween('paid_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])
            ->whereIn('status', ['paid', 'partial'])
            ->notVoided()
            ->select(
                DB::raw('DATE(paid_at) as date'),
                DB::raw('SUM(paid_amount) as total')
            )
            ->groupBy('date')
            ->pluck('total', 'date')
            ->toArray();

        // Build the trend data
        $period = CarbonPeriod::create($startDate, $endDate);

        return collect($period)->map(function (Carbon $date) use ($revenue) {
            $dateStr = $date->toDateString();

            return [
                'date' => $dateStr,
                'day' => $date->format('D'),
                'fullDate' => $date->format('M d'),
                'revenue' => (float) ($revenue[$dateStr] ?? 0),
            ];
        })->values();
    }

    /**
     * Get department activity within date range.
     *
     * @return Collection<int, array{name: string, checkins: int, color: string}>
     */
    protected function getDepartmentActivity(): Collection
    {
        [$startDate, $endDate] = $this->getDateRange();
        $startDateStr = $startDate->toDateString();
        $endDateStr = $endDate->toDateString();

        // Vibrant colors for departments
        $colors = [
            '#3b82f6', // blue
            '#10b981', // emerald
            '#f59e0b', // amber
            '#8b5cf6', // violet
            '#ec4899', // pink
            '#06b6d4', // cyan
            '#f97316', // orange
            '#84cc16', // lime
            '#6366f1', // indigo
            '#14b8a6', // teal
        ];

        return Department::query()
            ->active()
            ->select([
                'departments.id',
                'departments.name',
                'departments.code',
                DB::raw("(
                    SELECT COUNT(*)
                    FROM patient_checkins pc
                    WHERE pc.department_id = departments.id
                    AND DATE(pc.checked_in_at) BETWEEN '{$startDateStr}' AND '{$endDateStr}'
                    AND (pc.migrated_from_mittag = 0 OR pc.migrated_from_mittag IS NULL)
                ) as checkins"),
            ])
            ->orderByDesc('checkins')
            ->limit(8)
            ->get()
            ->values()
            ->map(function ($dept, $index) use ($colors) {
                return [
                    'name' => $dept->name,
                    'code' => $dept->code,
                    'checkins' => (int) $dept->checkins,
                    'fill' => $colors[$index % count($colors)],
                ];
            });
    }
}
