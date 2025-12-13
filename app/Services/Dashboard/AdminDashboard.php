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
     * Get metrics data for the admin dashboard.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(User $user): array
    {
        // Admin metrics are system-wide aggregates (5 min cache)
        return [
            'totalPatientsToday' => $this->cacheSystem('total_patients_today', fn () => $this->getTotalPatientsToday()),
            'totalRevenueToday' => $this->cacheSystem('total_revenue_today', fn () => $this->getTotalRevenueToday()),
            'activeUsersCount' => $this->cacheSystem('active_users_count', fn () => $this->getActiveUsersCount()),
            'totalDepartments' => $this->cacheSystem('total_departments', fn () => $this->getTotalActiveDepartments()),
        ];
    }

    /**
     * Get list data for the admin dashboard.
     *
     * @return array<string, Collection>
     */
    public function getListData(User $user): array
    {
        return [
            'patientFlowTrend' => $this->cacheSystem('patient_flow_trend', fn () => $this->getPatientFlowTrend()),
            'revenueTrend' => $this->cacheSystem('revenue_trend', fn () => $this->getRevenueTrend()),
            'departmentActivity' => $this->cacheSystem('department_activity', fn () => $this->getDepartmentActivity()),
        ];
    }

    /**
     * Get total patients checked in today.
     */
    protected function getTotalPatientsToday(): int
    {
        return PatientCheckin::query()
            ->whereDate('checked_in_at', today())
            ->count();
    }

    /**
     * Get total revenue collected today.
     */
    protected function getTotalRevenueToday(): float
    {
        return (float) Charge::query()
            ->whereDate('paid_at', today())
            ->whereIn('status', ['paid', 'partial'])
            ->notVoided()
            ->sum('paid_amount');
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
     * Get patient flow trend for the last 7 days.
     *
     * @return Collection<int, array{date: string, day: string, checkins: int, consultations: int}>
     */
    protected function getPatientFlowTrend(): Collection
    {
        $startDate = Carbon::today()->subDays(6);
        $endDate = Carbon::today();

        // Get check-ins per day
        $checkins = PatientCheckin::query()
            ->whereBetween('checked_in_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->select(
                DB::raw('DATE(checked_in_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // Get consultations per day
        $consultations = DB::table('consultations')
            ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // Build the trend data for all 7 days
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
     * Get revenue trend for the last 7 days.
     *
     * @return Collection<int, array{date: string, day: string, revenue: float}>
     */
    protected function getRevenueTrend(): Collection
    {
        $startDate = Carbon::today()->subDays(6);
        $endDate = Carbon::today();

        // Get revenue per day
        $revenue = Charge::query()
            ->whereBetween('paid_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->whereIn('status', ['paid', 'partial'])
            ->notVoided()
            ->select(
                DB::raw('DATE(paid_at) as date'),
                DB::raw('SUM(paid_amount) as total')
            )
            ->groupBy('date')
            ->pluck('total', 'date')
            ->toArray();

        // Build the trend data for all 7 days
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
     * Get department activity for today (for horizontal bar chart).
     *
     * @return Collection<int, array{name: string, checkins: int, color: string}>
     */
    protected function getDepartmentActivity(): Collection
    {
        $today = today()->toDateString();

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
                    AND DATE(pc.checked_in_at) = '{$today}'
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
