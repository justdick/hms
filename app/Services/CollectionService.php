<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\PaymentMethod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CollectionService
{
    /**
     * Get cashier's collections for a specific date.
     */
    public function getCashierCollections(User $cashier, Carbon $date): Collection
    {
        return Charge::where('processed_by', $cashier->id)
            ->whereDate('paid_at', $date)
            ->whereIn('status', ['paid', 'partial'])
            ->with(['patientCheckin.patient:id,first_name,last_name,patient_number'])
            ->orderBy('paid_at', 'desc')
            ->get();
    }

    /**
     * Get collections breakdown by payment method for a cashier on a specific date.
     */
    public function getCollectionsByPaymentMethod(User $cashier, Carbon $date): array
    {
        $collections = Charge::where('processed_by', $cashier->id)
            ->whereDate('paid_at', $date)
            ->whereIn('status', ['paid', 'partial'])
            ->selectRaw('
                COALESCE(metadata->>\'payment_method\', \'cash\') as payment_method,
                SUM(paid_amount) as total_amount,
                COUNT(*) as transaction_count
            ')
            ->groupBy('payment_method')
            ->get();

        // Get all payment methods for complete breakdown
        $paymentMethods = PaymentMethod::active()->get();

        $breakdown = [];
        foreach ($paymentMethods as $method) {
            $collection = $collections->firstWhere('payment_method', $method->code);
            $breakdown[$method->code] = [
                'name' => $method->name,
                'code' => $method->code,
                'total_amount' => $collection ? (float) $collection->total_amount : 0,
                'transaction_count' => $collection ? (int) $collection->transaction_count : 0,
            ];
        }

        return $breakdown;
    }

    /**
     * Get cashier's collection summary for a specific date.
     */
    public function getCashierCollectionSummary(User $cashier, Carbon $date): array
    {
        $collections = $this->getCashierCollections($cashier, $date);
        $breakdown = $this->getCollectionsByPaymentMethod($cashier, $date);

        $totalAmount = $collections->sum('paid_amount');
        $transactionCount = $collections->count();

        return [
            'cashier' => [
                'id' => $cashier->id,
                'name' => $cashier->name,
            ],
            'date' => $date->toDateString(),
            'total_amount' => $totalAmount,
            'transaction_count' => $transactionCount,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Get all cashiers' collections for a date range.
     */
    public function getAllCollections(Carbon $startDate, Carbon $endDate): Collection
    {
        return Charge::whereBetween('paid_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->whereIn('status', ['paid', 'partial'])
            ->whereNotNull('processed_by')
            ->with([
                'processedByUser:id,name',
                'patientCheckin.patient:id,first_name,last_name,patient_number',
            ])
            ->orderBy('paid_at', 'desc')
            ->get();
    }

    /**
     * Get collections grouped by cashier for a specific date or date range.
     */
    public function getCollectionsByCashier(Carbon $startDate, ?Carbon $endDate = null): Collection
    {
        $query = Charge::whereIn('status', ['paid', 'partial'])
            ->whereNotNull('processed_by');

        if ($endDate) {
            $query->whereBetween('paid_at', [$startDate->startOfDay(), $endDate->endOfDay()]);
        } else {
            $query->whereDate('paid_at', $startDate);
        }

        return $query->selectRaw('
                processed_by,
                SUM(paid_amount) as total_amount,
                COUNT(*) as transaction_count
            ')
            ->groupBy('processed_by')
            ->with('processedByUser:id,name')
            ->get()
            ->map(function ($item) {
                return [
                    'cashier_id' => $item->processed_by,
                    'cashier_name' => $item->processedByUser?->name ?? 'Unknown',
                    'total_amount' => (float) $item->total_amount,
                    'transaction_count' => (int) $item->transaction_count,
                ];
            });
    }

    /**
     * Get collections grouped by department for a date range.
     */
    public function getCollectionsByDepartment(Carbon $startDate, Carbon $endDate): Collection
    {
        return Charge::whereBetween('charges.paid_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->whereIn('charges.status', ['paid', 'partial'])
            ->join('patient_checkins', 'charges.patient_checkin_id', '=', 'patient_checkins.id')
            ->join('departments', 'patient_checkins.department_id', '=', 'departments.id')
            ->selectRaw('
                departments.id as department_id,
                departments.name as department_name,
                SUM(charges.paid_amount) as total_amount,
                COUNT(*) as transaction_count
            ')
            ->groupBy('departments.id', 'departments.name')
            ->get()
            ->map(function ($item) {
                return [
                    'department_id' => $item->department_id,
                    'department_name' => $item->department_name,
                    'total_amount' => (float) $item->total_amount,
                    'transaction_count' => (int) $item->transaction_count,
                ];
            });
    }

    /**
     * Get detailed transactions for a cashier on a specific date.
     */
    public function getCashierTransactions(User $cashier, Carbon $date): Collection
    {
        return $this->getCashierCollections($cashier, $date)
            ->map(function ($charge) {
                return [
                    'id' => $charge->id,
                    'receipt_number' => $charge->receipt_number,
                    'patient' => $charge->patientCheckin?->patient ? [
                        'id' => $charge->patientCheckin->patient->id,
                        'name' => $charge->patientCheckin->patient->first_name.' '.$charge->patientCheckin->patient->last_name,
                        'patient_number' => $charge->patientCheckin->patient->patient_number,
                    ] : null,
                    'description' => $charge->description,
                    'service_type' => $charge->service_type,
                    'amount' => (float) $charge->amount,
                    'paid_amount' => (float) $charge->paid_amount,
                    'payment_method' => $charge->metadata['payment_method'] ?? 'cash',
                    'paid_at' => $charge->paid_at?->format('Y-m-d H:i:s'),
                    'status' => $charge->status,
                ];
            });
    }
}
