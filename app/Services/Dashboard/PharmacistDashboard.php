<?php

namespace App\Services\Dashboard;

use App\Models\Drug;
use App\Models\Prescription;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Dashboard widget for pharmacist metrics and data.
 *
 * Provides prescription queue, dispensing metrics, and low stock alerts
 * for users with dispensing and inventory viewing permissions.
 */
class PharmacistDashboard extends AbstractDashboardWidget
{
    /**
     * Get the unique identifier for this widget.
     */
    public function getWidgetId(): string
    {
        return 'prescription_queue';
    }

    /**
     * Get the required permissions to view this widget.
     *
     * @return array<string>
     */
    public function getRequiredPermissions(): array
    {
        return ['dispensing.view', 'dispensing.review', 'dispensing.process', 'inventory.view'];
    }

    /**
     * Get metrics data for the pharmacist dashboard.
     *
     * @return array<string, int>
     */
    public function getMetrics(User $user): array
    {
        return [
            'pendingPrescriptions' => $this->cacheSystem('pending_prescriptions', fn () => $this->getPendingPrescriptionsCount()),
            'dispensedToday' => $this->cacheSystem('dispensed_today', fn () => $this->getDispensedTodayCount()),
            'lowStockCount' => $this->cacheSystem('low_stock_count', fn () => $this->getLowStockCountInternal()),
            'expiringCount' => $this->cacheSystem('expiring_count', fn () => $this->getExpiringCountInternal()),
        ];
    }

    /**
     * Get list data for the pharmacist dashboard.
     *
     * @return array<string, Collection>
     */
    public function getListData(User $user): array
    {
        return [
            'urgentPrescriptions' => $this->cacheSystem('urgent_prescriptions', fn () => $this->getUrgentPrescriptions()),
            'lowStockItems' => $this->cacheSystem('low_stock_items', fn () => $this->getLowStockItemsInternal()),
        ];
    }

    /**
     * Get count of pending prescriptions (awaiting review).
     */
    protected function getPendingPrescriptionsCount(): int
    {
        return Prescription::query()
            ->where('status', 'prescribed')
            ->whereNull('reviewed_at')
            ->whereNotNull('drug_id')
            ->count();
    }

    /**
     * Get count of prescriptions dispensed today.
     */
    protected function getDispensedTodayCount(): int
    {
        return Prescription::query()
            ->where('status', 'dispensed')
            ->whereDate('updated_at', today())
            ->count();
    }

    /**
     * Get count of batches expiring within 30 days.
     */
    protected function getExpiringCountInternal(): int
    {
        return \App\Models\DrugBatch::query()
            ->where('quantity_remaining', '>', 0)
            ->whereBetween('expiry_date', [now(), now()->addDays(30)])
            ->count();
    }

    /**
     * Get count of drugs with low stock (permission check wrapper).
     */
    protected function getLowStockCount(User $user): int
    {
        // Only show if user has inventory permission
        if (! $user->can('inventory.view')) {
            return 0;
        }

        return $this->getLowStockCountInternal();
    }

    /**
     * Get count of drugs with low stock (internal, for caching).
     * Optimized to use a single query with subquery for stock calculation.
     */
    protected function getLowStockCountInternal(): int
    {
        return Drug::query()
            ->where('is_active', true)
            ->whereNotNull('minimum_stock_level')
            ->where('minimum_stock_level', '>', 0)
            ->whereRaw('(
                SELECT COALESCE(SUM(db.quantity_remaining), 0)
                FROM drug_batches db
                WHERE db.drug_id = drugs.id
                AND db.quantity_remaining > 0
                AND (db.expiry_date IS NULL OR db.expiry_date > NOW())
            ) < minimum_stock_level')
            ->count();
    }

    /**
     * Get urgent/oldest prescriptions for dashboard display.
     *
     * @return Collection<int, array{
     *     id: int,
     *     patient_name: string,
     *     drug_name: string,
     *     quantity: int,
     *     wait_time: string,
     *     is_urgent: bool
     * }>
     */
    protected function getUrgentPrescriptions(): Collection
    {
        return Prescription::query()
            ->where('status', 'prescribed')
            ->whereNull('reviewed_at')
            ->whereNotNull('drug_id')
            ->with([
                'drug',
                'prescribable.patientCheckin.patient',
            ])
            ->orderBy('created_at')
            ->limit(10)
            ->get()
            ->map(function (Prescription $prescription) {
                $patient = $this->getPatientFromPrescription($prescription);
                $minutes = $prescription->created_at?->diffInMinutes(now()) ?? 0;

                return [
                    'id' => $prescription->id,
                    'patient_name' => $patient?->full_name ?? 'Unknown',
                    'drug_name' => $prescription->drug?->name ?? $prescription->medication_name ?? 'Unknown',
                    'quantity' => $prescription->quantity ?? 0,
                    'wait_time' => $this->formatWaitTime($minutes),
                    'is_urgent' => $prescription->is_urgent ?? false,
                ];
            });
    }

    /**
     * Format wait time as human-readable string.
     */
    protected function formatWaitTime(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.'m';
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        return $hours.'h '.$mins.'m';
    }

    /**
     * Get low stock items list (permission check wrapper).
     *
     * @return Collection<int, array{
     *     id: int,
     *     name: string,
     *     drug_code: string|null,
     *     current_stock: int,
     *     minimum_level: int,
     *     category: string|null,
     *     unit_type: string|null
     * }>
     */
    protected function getLowStockItems(User $user): Collection
    {
        // Only show if user has inventory permission
        if (! $user->can('inventory.view')) {
            return collect();
        }

        return $this->getLowStockItemsInternal();
    }

    /**
     * Get low stock items list (internal, for caching).
     * Optimized to calculate stock in a single query using subquery.
     *
     * @return Collection<int, array{
     *     id: int,
     *     name: string,
     *     drug_code: string|null,
     *     current_stock: int,
     *     minimum_level: int,
     *     category: string|null,
     *     unit_type: string|null
     * }>
     */
    protected function getLowStockItemsInternal(): Collection
    {
        return Drug::query()
            ->select([
                'drugs.*',
                \Illuminate\Support\Facades\DB::raw('(
                    SELECT COALESCE(SUM(db.quantity_remaining), 0)
                    FROM drug_batches db
                    WHERE db.drug_id = drugs.id
                    AND db.quantity_remaining > 0
                    AND (db.expiry_date IS NULL OR db.expiry_date > NOW())
                ) as calculated_stock'),
            ])
            ->where('is_active', true)
            ->whereNotNull('minimum_stock_level')
            ->where('minimum_stock_level', '>', 0)
            ->havingRaw('calculated_stock < minimum_stock_level')
            ->orderByRaw('(minimum_stock_level - calculated_stock) DESC')
            ->limit(10)
            ->get()
            ->map(fn (Drug $drug) => [
                'id' => $drug->id,
                'name' => $drug->name,
                'drug_code' => $drug->drug_code,
                'current_stock' => (int) $drug->calculated_stock,
                'minimum_level' => $drug->minimum_stock_level,
                'category' => $drug->category,
                'unit_type' => $drug->unit_type,
            ]);
    }

    /**
     * Get patient from prescription through polymorphic relationship.
     */
    protected function getPatientFromPrescription(Prescription $prescription): ?\App\Models\Patient
    {
        $prescribable = $prescription->prescribable;

        if (! $prescribable) {
            return null;
        }

        // Handle Consultation prescribable
        if ($prescribable instanceof \App\Models\Consultation) {
            return $prescribable->patientCheckin?->patient;
        }

        // Handle PatientAdmission prescribable
        if ($prescribable instanceof \App\Models\PatientAdmission) {
            return $prescribable->patient;
        }

        // Handle WardRound prescribable
        if (method_exists($prescribable, 'patientAdmission')) {
            return $prescribable->patientAdmission?->patient;
        }

        return null;
    }

    /**
     * Get doctor from prescription through polymorphic relationship.
     */
    protected function getDoctorFromPrescription(Prescription $prescription): ?\App\Models\User
    {
        $prescribable = $prescription->prescribable;

        if (! $prescribable) {
            return null;
        }

        // Handle Consultation prescribable
        if ($prescribable instanceof \App\Models\Consultation) {
            return $prescribable->doctor;
        }

        // Handle WardRound prescribable
        if (method_exists($prescribable, 'doctor')) {
            return $prescribable->doctor;
        }

        return null;
    }
}
