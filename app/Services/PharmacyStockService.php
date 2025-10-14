<?php

namespace App\Services;

use App\Models\Drug;
use App\Models\DrugBatch;
use App\Models\Prescription;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PharmacyStockService
{
    /**
     * Check if sufficient stock is available for a drug.
     */
    public function checkAvailability(Drug $drug, int $quantity): array
    {
        $cacheKey = "drug_stock_{$drug->id}";

        $inStock = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($drug) {
            return DrugBatch::where('drug_id', $drug->id)
                ->where('quantity_remaining', '>', 0)
                ->where(function ($query) {
                    $query->whereNull('expiry_date')
                        ->orWhere('expiry_date', '>', now());
                })
                ->sum('quantity_remaining');
        });

        $available = $inStock >= $quantity;
        $shortage = $available ? 0 : $quantity - $inStock;

        return [
            'available' => $available,
            'in_stock' => $inStock,
            'shortage' => $shortage,
        ];
    }

    /**
     * Get available batches for a drug sorted by expiry date (FIFO).
     */
    public function getAvailableBatches(Drug $drug, int $quantity): Collection
    {
        return DrugBatch::where('drug_id', $drug->id)
            ->where('quantity_remaining', '>', 0)
            ->where(function ($query) {
                $query->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>', now());
            })
            ->orderBy('expiry_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Reserve stock for a prescription (soft reservation).
     */
    public function reserveStock(Drug $drug, int $quantity, Prescription $prescription): bool
    {
        $availability = $this->checkAvailability($drug, $quantity);

        if (! $availability['available']) {
            return false;
        }

        // Store reservation in prescription metadata or create a reservations table
        // For now, we'll just track it in the prescription's quantity_to_dispense field
        $prescription->update([
            'quantity_to_dispense' => $quantity,
        ]);

        // Clear cache to reflect reserved stock
        Cache::forget("drug_stock_{$drug->id}");

        return true;
    }

    /**
     * Release stock reservation for a prescription.
     */
    public function releaseReservation(Prescription $prescription): bool
    {
        if (! $prescription->drug_id) {
            return false;
        }

        $prescription->update([
            'quantity_to_dispense' => null,
        ]);

        // Clear cache
        Cache::forget("drug_stock_{$prescription->drug_id}");

        return true;
    }

    /**
     * Deduct stock from batches (actual dispensing).
     */
    public function deductStock(Drug $drug, int $quantity): array
    {
        $batches = $this->getAvailableBatches($drug, $quantity);
        $remaining = $quantity;
        $deducted = [];

        DB::transaction(function () use ($batches, &$remaining, &$deducted) {
            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $toDeduct = min($remaining, $batch->quantity_remaining);
                $batch->decrement('quantity_remaining', $toDeduct);

                $deducted[] = [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'quantity' => $toDeduct,
                    'expiry_date' => $batch->expiry_date,
                ];

                $remaining -= $toDeduct;
            }
        });

        // Clear cache
        Cache::forget("drug_stock_{$drug->id}");

        return [
            'success' => $remaining === 0,
            'deducted' => $deducted,
            'remaining_needed' => $remaining,
        ];
    }

    /**
     * Get stock status indicator.
     */
    public function getStockStatus(Drug $drug, int $requestedQuantity): string
    {
        $availability = $this->checkAvailability($drug, $requestedQuantity);

        if ($availability['available']) {
            return 'in_stock';
        }

        if ($availability['in_stock'] > 0) {
            return 'partial';
        }

        return 'out_of_stock';
    }

    /**
     * Check if any batch is expiring soon (within 30 days).
     */
    public function hasExpiringBatches(Drug $drug): bool
    {
        return DrugBatch::where('drug_id', $drug->id)
            ->where('quantity_remaining', '>', 0)
            ->where('expiry_date', '>', now())
            ->where('expiry_date', '<=', now()->addDays(30))
            ->exists();
    }
}
