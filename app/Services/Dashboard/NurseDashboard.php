<?php

namespace App\Services\Dashboard;

use App\Models\MedicationAdministration;
use App\Models\PatientAdmission;
use App\Models\PatientCheckin;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Dashboard widget for nurse metrics and data.
 *
 * Provides vitals queue, medication schedule, and active admissions
 * for users with vitals and medication administration permissions.
 */
class NurseDashboard extends AbstractDashboardWidget
{
    /**
     * Get the unique identifier for this widget.
     */
    public function getWidgetId(): string
    {
        return 'vitals_queue';
    }

    /**
     * Get the required permissions to view this widget.
     *
     * @return array<string>
     */
    public function getRequiredPermissions(): array
    {
        return ['vitals.view-all', 'vitals.view-dept', 'view medication administrations', 'administer medications'];
    }

    /**
     * Get metrics data for the nurse dashboard.
     *
     * @return array<string, int>
     */
    public function getMetrics(User $user): array
    {
        return $this->cacheForUser($user, 'metrics', fn () => [
            'awaitingVitals' => $this->getAwaitingVitalsCount($user),
            'pendingMedications' => $this->getPendingMedicationsCount($user),
            'activeAdmissions' => $this->getActiveAdmissionsCount($user),
            'vitalsRecordedToday' => $this->getVitalsRecordedTodayCount($user),
        ]);
    }

    /**
     * Get list data for the nurse dashboard.
     *
     * @return array<string, Collection>
     */
    public function getListData(User $user): array
    {
        return [
            'vitalsQueue' => $this->cacheForUser($user, 'vitals_queue', fn () => $this->getVitalsQueueSimplified($user)),
            'medicationSchedule' => $this->cacheForUser($user, 'medication_schedule', fn () => $this->getMedicationScheduleSimplified($user)),
        ];
    }

    /**
     * Get count of patients awaiting vitals (checked in but no vitals taken).
     */
    protected function getAwaitingVitalsCount(User $user): int
    {
        return $this->getVitalsQueueQuery($user)->count();
    }

    /**
     * Get count of pending medication administrations (due within 1 hour).
     */
    protected function getPendingMedicationsCount(User $user): int
    {
        return $this->getMedicationScheduleQuery($user)->count();
    }

    /**
     * Get count of active admissions in assigned wards.
     */
    protected function getActiveAdmissionsCount(User $user): int
    {
        return PatientAdmission::query()
            ->where('status', 'admitted')
            ->count();
    }

    /**
     * Get count of vitals recorded today.
     */
    protected function getVitalsRecordedTodayCount(User $user): int
    {
        $query = PatientCheckin::query()
            ->whereDate('checked_in_at', today())
            ->whereIn('status', ['vitals_taken', 'awaiting_consultation', 'in_consultation', 'completed']);

        if (! $this->canViewAll($user, 'vitals')) {
            $query->whereIn('department_id', $this->getUserDepartmentIds($user));
        }

        return $query->count();
    }

    /**
     * Get simplified vitals queue for dashboard display.
     *
     * @return Collection<int, array{
     *     id: int,
     *     patient_name: string,
     *     department: string,
     *     wait_time: string,
     *     is_urgent: bool
     * }>
     */
    protected function getVitalsQueueSimplified(User $user): Collection
    {
        return $this->getVitalsQueueQuery($user)
            ->with(['patient', 'department'])
            ->orderBy('checked_in_at')
            ->limit(10)
            ->get()
            ->map(fn (PatientCheckin $checkin) => [
                'id' => $checkin->id,
                'patient_name' => $checkin->patient->full_name ?? 'Unknown',
                'department' => $checkin->department->name ?? 'Unknown',
                'wait_time' => $this->calculateWaitTime($checkin->checked_in_at),
                'is_urgent' => $checkin->checked_in_at?->diffInMinutes(now()) > 15,
            ]);
    }

    /**
     * Get simplified medication schedule for dashboard display.
     *
     * @return Collection<int, array{
     *     id: int,
     *     patient_name: string,
     *     ward: string,
     *     bed: string|null,
     *     medication: string,
     *     dosage: string|null,
     *     scheduled_time: string,
     *     is_overdue: bool
     * }>
     */
    protected function getMedicationScheduleSimplified(User $user): Collection
    {
        return $this->getMedicationScheduleQuery($user)
            ->with([
                'prescription:id,drug_id,medication_name,dose_quantity',
                'prescription.drug:id,name,unit_type',
                'patientAdmission:id,patient_id,ward_id,bed_id',
                'patientAdmission.patient:id,first_name,last_name',
                'patientAdmission.ward:id,name',
                'patientAdmission.bed:id,bed_number',
            ])
            ->orderBy('scheduled_time')
            ->limit(10)
            ->get()
            ->map(function (MedicationAdministration $administration) {
                $admission = $administration->patientAdmission;
                $prescription = $administration->prescription;

                return [
                    'id' => $administration->id,
                    'patient_name' => $admission?->patient?->full_name ?? 'Unknown',
                    'ward' => $admission?->ward?->name ?? 'Unknown',
                    'bed' => $admission?->bed?->bed_number ?? null,
                    'medication' => $prescription?->drug?->name ?? $prescription?->medication_name ?? 'Unknown',
                    'dosage' => $prescription?->dose_quantity ? "{$prescription->dose_quantity} {$prescription->drug?->unit_type}" : null,
                    'scheduled_time' => $administration->scheduled_time?->format('H:i'),
                    'is_overdue' => $administration->scheduled_time?->isPast() ?? false,
                ];
            });
    }

    /**
     * Get the base query for vitals queue.
     */
    protected function getVitalsQueueQuery(User $user): \Illuminate\Database\Eloquent\Builder
    {
        $query = PatientCheckin::query()
            ->whereDate('checked_in_at', today())
            ->where('status', 'checked_in');

        // Apply department filtering if user doesn't have full access
        if (! $this->canViewAll($user, 'vitals')) {
            $query->whereIn('department_id', $this->getUserDepartmentIds($user));
        }

        return $query;
    }

    /**
     * Get the base query for medication schedule.
     */
    protected function getMedicationScheduleQuery(User $user): \Illuminate\Database\Eloquent\Builder
    {
        $query = MedicationAdministration::query()
            ->where('status', 'scheduled')
            ->where('scheduled_time', '<=', now()->addHours(2))
            ->whereHas('patientAdmission', function ($q) {
                $q->where('status', 'admitted');
            });

        // Filter by user access - for now, show all scheduled medications
        // In a more complex setup, this could filter by assigned wards
        if (! $this->hasFullAccess($user) && ! $user->can('view medication administrations')) {
            // No access
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    /**
     * Calculate wait time from check-in.
     */
    protected function calculateWaitTime(?\Illuminate\Support\Carbon $checkedInAt): string
    {
        if (! $checkedInAt) {
            return '-';
        }

        $minutes = $checkedInAt->diffInMinutes(now());

        if ($minutes < 60) {
            return "{$minutes}m";
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return "{$hours}h {$remainingMinutes}m";
    }
}
