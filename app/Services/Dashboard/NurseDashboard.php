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
 * Provides vitals queue, medication activity, and active admissions
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
            'medicationsGivenToday' => $this->getMedicationsGivenTodayCount($user),
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
            'recentMedications' => $this->cacheForUser($user, 'recent_medications', fn () => $this->getRecentMedicationsSimplified($user)),
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
     * Get count of medications given today.
     */
    protected function getMedicationsGivenTodayCount(User $user): int
    {
        return MedicationAdministration::query()
            ->whereDate('administered_at', today())
            ->where('status', 'given')
            ->whereHas('patientAdmission', function ($q) {
                $q->where('status', 'admitted');
            })
            ->count();
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
     * Get recent medication administrations for dashboard display.
     *
     * @return Collection<int, array{
     *     id: int,
     *     patient_name: string,
     *     ward: string,
     *     bed: string|null,
     *     medication: string,
     *     dosage: string|null,
     *     administered_at: string,
     *     status: string
     * }>
     */
    protected function getRecentMedicationsSimplified(User $user): Collection
    {
        return MedicationAdministration::query()
            ->whereDate('administered_at', today())
            ->whereHas('patientAdmission', function ($q) {
                $q->where('status', 'admitted');
            })
            ->with([
                'prescription:id,drug_id,medication_name,dose_quantity',
                'prescription.drug:id,name,unit_type',
                'patientAdmission:id,patient_id,ward_id,bed_id',
                'patientAdmission.patient:id,first_name,last_name',
                'patientAdmission.ward:id,name',
                'patientAdmission.bed:id,bed_number',
            ])
            ->orderBy('administered_at', 'desc')
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
                    'administered_at' => $administration->administered_at?->format('H:i'),
                    'status' => $administration->status,
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
