<?php

namespace App\Services\Dashboard;

use App\Models\PatientCheckin;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Dashboard widget for receptionist metrics and data.
 *
 * Provides check-in metrics and recent check-ins list for users
 * with check-in viewing permissions.
 */
class ReceptionistDashboard extends AbstractDashboardWidget
{
    /**
     * Get the unique identifier for this widget.
     */
    public function getWidgetId(): string
    {
        return 'checkin_metrics';
    }

    /**
     * Get the required permissions to view this widget.
     *
     * @return array<string>
     */
    public function getRequiredPermissions(): array
    {
        return ['checkins.view-all', 'checkins.view-dept'];
    }

    /**
     * Get metrics data for the receptionist dashboard.
     *
     * @return array<string, int>
     */
    public function getMetrics(User $user): array
    {
        return $this->cacheForUser($user, 'metrics', function () use ($user) {
            $query = $this->getBaseQuery($user);

            return [
                'todayCheckins' => (clone $query)->count(),
                'awaitingVitals' => (clone $query)
                    ->where('status', 'checked_in')
                    ->count(),
                'awaitingConsultation' => (clone $query)
                    ->whereIn('status', ['vitals_taken', 'awaiting_consultation'])
                    ->count(),
                'completedToday' => (clone $query)
                    ->where('status', 'completed')
                    ->count(),
            ];
        });
    }

    /**
     * Get list data for the receptionist dashboard.
     *
     * @return array<string, Collection>
     */
    public function getListData(User $user): array
    {
        return [
            'waitingPatients' => $this->cacheForUser($user, 'waiting_patients', fn () => $this->getWaitingPatients($user)),
        ];
    }

    /**
     * Get the base query for check-ins, filtered by user access.
     */
    protected function getBaseQuery(User $user): \Illuminate\Database\Eloquent\Builder
    {
        $query = PatientCheckin::query()
            ->whereDate('checked_in_at', today());

        // Apply department filtering if user doesn't have full access
        if (! $this->canViewAll($user, 'checkins')) {
            $query->whereIn('department_id', $this->getUserDepartmentIds($user));
        }

        return $query;
    }

    /**
     * Get patients waiting longest (for dashboard display).
     *
     * @return Collection<int, array{
     *     id: int,
     *     patient_name: string,
     *     department: string,
     *     wait_time: string,
     *     status: string
     * }>
     */
    protected function getWaitingPatients(User $user): Collection
    {
        $query = $this->getBaseQuery($user);

        return $query
            ->with(['patient', 'department'])
            ->whereIn('status', ['checked_in', 'vitals_taken', 'awaiting_consultation'])
            ->orderBy('checked_in_at')
            ->limit(10)
            ->get()
            ->map(fn (PatientCheckin $checkin) => [
                'id' => $checkin->id,
                'patient_name' => $checkin->patient->full_name ?? 'Unknown',
                'department' => $checkin->department->name ?? 'Unknown',
                'wait_time' => $this->formatWaitTime($checkin->checked_in_at),
                'status' => $checkin->status,
            ]);
    }

    /**
     * Format wait time as human-readable string.
     */
    protected function formatWaitTime(?\Carbon\Carbon $checkedInAt): string
    {
        if (! $checkedInAt) {
            return '-';
        }

        $minutes = $checkedInAt->diffInMinutes(now());

        if ($minutes < 60) {
            return $minutes.'m';
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        return $hours.'h '.$mins.'m';
    }
}
