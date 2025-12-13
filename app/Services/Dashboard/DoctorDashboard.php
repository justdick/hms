<?php

namespace App\Services\Dashboard;

use App\Models\Consultation;
use App\Models\LabOrder;
use App\Models\PatientCheckin;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Dashboard widget for doctor metrics and data.
 *
 * Provides consultation queue, active consultations, and pending lab results
 * for users with consultation viewing permissions.
 */
class DoctorDashboard extends AbstractDashboardWidget
{
    /**
     * Get the unique identifier for this widget.
     */
    public function getWidgetId(): string
    {
        return 'consultation_queue';
    }

    /**
     * Get the required permissions to view this widget.
     *
     * @return array<string>
     */
    public function getRequiredPermissions(): array
    {
        return ['consultations.view-all', 'consultations.view-dept'];
    }

    /**
     * Get metrics data for the doctor dashboard.
     *
     * @return array<string, int>
     */
    public function getMetrics(User $user): array
    {
        return $this->cacheForUser($user, 'metrics', fn () => [
            'consultationQueue' => $this->getConsultationQueueCount($user),
            'activeConsultations' => $this->getActiveConsultationsCount($user),
            'pendingLabResults' => $this->getPendingLabResultsCount($user),
            'completedToday' => $this->getCompletedTodayCount($user),
        ]);
    }

    /**
     * Get list data for the doctor dashboard.
     *
     * @return array<string, Collection>
     */
    public function getListData(User $user): array
    {
        return [
            'nextPatients' => $this->cacheForUser($user, 'next_patients', fn () => $this->getNextPatients($user)),
        ];
    }

    /**
     * Get count of patients awaiting consultation.
     */
    protected function getConsultationQueueCount(User $user): int
    {
        return $this->getConsultationQueueQuery($user)->count();
    }

    /**
     * Get count of active (in-progress) consultations.
     */
    protected function getActiveConsultationsCount(User $user): int
    {
        $query = Consultation::query()
            ->where('status', 'in_progress')
            ->whereDate('started_at', today());

        // Filter by user access
        if ($this->canViewAll($user, 'consultations')) {
            // No filter needed
        } elseif ($user->can('consultations.view-own')) {
            $query->where('doctor_id', $user->id);
        } elseif ($user->can('consultations.view-dept')) {
            $query->whereHas('patientCheckin', function ($q) use ($user) {
                $q->whereIn('department_id', $this->getUserDepartmentIds($user));
            });
        } else {
            return 0;
        }

        return $query->count();
    }

    /**
     * Get count of pending lab results for the doctor's patients.
     */
    protected function getPendingLabResultsCount(User $user): int
    {
        return $this->getPendingLabResultsQuery($user)->count();
    }

    /**
     * Get count of consultations completed today.
     */
    protected function getCompletedTodayCount(User $user): int
    {
        $query = Consultation::query()
            ->where('status', 'completed')
            ->whereDate('completed_at', today());

        if ($this->canViewAll($user, 'consultations')) {
            // No filter needed
        } elseif ($user->can('consultations.view-own')) {
            $query->where('doctor_id', $user->id);
        } elseif ($user->can('consultations.view-dept')) {
            $query->whereHas('patientCheckin', function ($q) use ($user) {
                $q->whereIn('department_id', $this->getUserDepartmentIds($user));
            });
        } else {
            return 0;
        }

        return $query->count();
    }

    /**
     * Get next patients ready for consultation (simplified for dashboard).
     *
     * @return Collection<int, array{
     *     id: int,
     *     patient_name: string,
     *     patient_number: string,
     *     department: string,
     *     chief_complaint: string|null,
     *     wait_time: string
     * }>
     */
    protected function getNextPatients(User $user): Collection
    {
        return $this->getConsultationQueueQuery($user)
            ->with(['patient', 'department'])
            ->orderBy('vitals_taken_at')
            ->limit(5)
            ->get()
            ->map(fn (PatientCheckin $checkin) => [
                'id' => $checkin->id,
                'patient_name' => $checkin->patient->full_name ?? 'Unknown',
                'patient_number' => $checkin->patient->patient_number ?? '',
                'department' => $checkin->department->name ?? 'Unknown',
                'chief_complaint' => $checkin->chief_complaint,
                'wait_time' => $this->calculateWaitTime($checkin->vitals_taken_at),
            ]);
    }

    /**
     * Get pending lab results awaiting review.
     * Optimized with proper eager loading for polymorphic relationships.
     *
     * @return Collection<int, array{
     *     id: int,
     *     patient_name: string,
     *     patient_number: string,
     *     test_name: string,
     *     ordered_at: string|null,
     *     result_entered_at: string|null,
     *     priority: string
     * }>
     */
    protected function getPendingLabResults(User $user): Collection
    {
        return $this->getPendingLabResultsQuery($user)
            ->with([
                'labService:id,name',
                'orderable' => function ($morphTo) {
                    $morphTo->morphWith([
                        Consultation::class => ['patientCheckin:id,patient_id', 'patientCheckin.patient:id,first_name,last_name,patient_number'],
                    ]);
                },
            ])
            ->orderByDesc('result_entered_at')
            ->limit(10)
            ->get()
            ->map(function (LabOrder $labOrder) {
                $patient = $this->getPatientFromLabOrder($labOrder);

                return [
                    'id' => $labOrder->id,
                    'patient_name' => $patient?->full_name ?? 'Unknown',
                    'patient_number' => $patient?->patient_number ?? '',
                    'test_name' => $labOrder->labService->name ?? 'Unknown Test',
                    'ordered_at' => $labOrder->ordered_at?->format('M d, H:i'),
                    'result_entered_at' => $labOrder->result_entered_at?->format('M d, H:i'),
                    'priority' => $labOrder->priority ?? 'routine',
                ];
            });
    }

    /**
     * Get the base query for consultation queue.
     */
    protected function getConsultationQueueQuery(User $user): \Illuminate\Database\Eloquent\Builder
    {
        $query = PatientCheckin::query()
            ->whereDate('checked_in_at', today())
            ->whereIn('status', ['vitals_taken', 'awaiting_consultation']);

        // Apply department filtering if user doesn't have full access
        if (! $this->canViewAll($user, 'consultations')) {
            $query->whereIn('department_id', $this->getUserDepartmentIds($user));
        }

        return $query;
    }

    /**
     * Get the base query for pending lab results.
     */
    protected function getPendingLabResultsQuery(User $user): \Illuminate\Database\Eloquent\Builder
    {
        $query = LabOrder::query()
            ->where('status', 'completed')
            ->whereNotNull('result_entered_at');

        // Filter by user access - show results for consultations the doctor can access
        if ($this->canViewAll($user, 'lab-orders')) {
            // No filter needed
        } elseif ($user->can('consultations.view-own')) {
            // Show lab results for the doctor's own consultations
            $query->where(function ($q) use ($user) {
                $q->whereHasMorph('orderable', [Consultation::class], function ($q) use ($user) {
                    $q->where('doctor_id', $user->id);
                });
            });
        } elseif ($user->can('consultations.view-dept') || $user->can('lab-orders.view-dept')) {
            // Show lab results for consultations in the doctor's departments
            $departmentIds = $this->getUserDepartmentIds($user);
            $query->where(function ($q) use ($departmentIds) {
                $q->whereHasMorph('orderable', [Consultation::class], function ($q) use ($departmentIds) {
                    $q->whereHas('patientCheckin', function ($q) use ($departmentIds) {
                        $q->whereIn('department_id', $departmentIds);
                    });
                });
            });
        } else {
            // No access
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    /**
     * Calculate wait time from vitals taken.
     */
    protected function calculateWaitTime(?\Illuminate\Support\Carbon $vitalsTakenAt): string
    {
        if (! $vitalsTakenAt) {
            return '-';
        }

        $minutes = $vitalsTakenAt->diffInMinutes(now());

        if ($minutes < 60) {
            return "{$minutes}m";
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return "{$hours}h {$remainingMinutes}m";
    }

    /**
     * Get patient from lab order through polymorphic relationship.
     */
    protected function getPatientFromLabOrder(LabOrder $labOrder): ?\App\Models\Patient
    {
        if ($labOrder->orderable instanceof Consultation) {
            return $labOrder->orderable->patientCheckin?->patient;
        }

        // Handle WardRound orderable
        if ($labOrder->orderable && method_exists($labOrder->orderable, 'patientAdmission')) {
            return $labOrder->orderable->patientAdmission?->patient;
        }

        return null;
    }
}
