<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillAdjustment;
use App\Models\Charge;
use App\Services\BillAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillAdjustmentController extends Controller
{
    public function __construct(
        private BillAdjustmentService $billAdjustmentService
    ) {}

    /**
     * Waive a charge completely
     */
    public function waive(Request $request, Charge $charge)
    {
        $this->authorize('waive', $charge);

        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:500',
        ]);

        // Validate charge status
        if (! $charge->isPending()) {
            return back()->withErrors([
                'error' => 'Cannot waive a charge that is not pending',
            ]);
        }

        try {
            DB::transaction(function () use ($charge, $validated) {
                // Create bill adjustment record
                BillAdjustment::create([
                    'charge_id' => $charge->id,
                    'adjustment_type' => 'waiver',
                    'original_amount' => $charge->amount,
                    'adjustment_amount' => $charge->amount,
                    'final_amount' => 0,
                    'reason' => $validated['reason'],
                    'adjusted_by' => auth()->id(),
                    'adjusted_at' => now(),
                ]);

                // Update charge
                $charge->update([
                    'is_waived' => true,
                    'waived_by' => auth()->id(),
                    'waived_at' => now(),
                    'waived_reason' => $validated['reason'],
                    'status' => 'waived',
                    'original_amount' => $charge->amount,
                    'adjustment_amount' => $charge->amount,
                ]);

                // Log to audit trail
                Log::channel('stack')->info('Charge waived', [
                    'charge_id' => $charge->id,
                    'original_amount' => $charge->amount,
                    'waived_by' => auth()->id(),
                    'waived_by_name' => auth()->user()->name,
                    'reason' => $validated['reason'],
                    'patient_checkin_id' => $charge->patient_checkin_id,
                    'service_type' => $charge->service_type,
                    'timestamp' => now(),
                ]);
            });

            return back()->with('success', 'Charge waived successfully');
        } catch (\Exception $e) {
            Log::error('Charge waiver failed', [
                'charge_id' => $charge->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to waive charge. Please try again.',
            ]);
        }
    }

    /**
     * Apply discount to a charge
     */
    public function adjust(Request $request, Charge $charge)
    {
        $this->authorize('adjust', $charge);

        $validated = $request->validate([
            'adjustment_type' => 'required|in:discount_percentage,discount_fixed',
            'adjustment_value' => 'required|numeric|min:0',
            'reason' => 'required|string|min:10|max:500',
        ]);

        // Validate charge status
        if (! $charge->isPending()) {
            return back()->withErrors([
                'error' => 'Cannot adjust a charge that is not pending',
            ]);
        }

        // Validate adjustment value
        if ($validated['adjustment_type'] === 'discount_fixed' && $validated['adjustment_value'] > $charge->amount) {
            return back()->withErrors([
                'adjustment_value' => 'Discount amount cannot exceed charge amount',
            ]);
        }

        if ($validated['adjustment_type'] === 'discount_percentage' && $validated['adjustment_value'] > 100) {
            return back()->withErrors([
                'adjustment_value' => 'Discount percentage cannot exceed 100%',
            ]);
        }

        try {
            // Calculate final amount
            $finalAmount = $this->billAdjustmentService->calculateAdjustedAmount(
                $charge->amount,
                $validated['adjustment_type'],
                $validated['adjustment_value']
            );

            $adjustmentAmount = $charge->amount - $finalAmount;

            DB::transaction(function () use ($charge, $validated, $finalAmount, $adjustmentAmount) {
                // Create adjustment record
                BillAdjustment::create([
                    'charge_id' => $charge->id,
                    'adjustment_type' => $validated['adjustment_type'],
                    'original_amount' => $charge->amount,
                    'adjustment_amount' => $adjustmentAmount,
                    'final_amount' => $finalAmount,
                    'reason' => $validated['reason'],
                    'adjusted_by' => auth()->id(),
                    'adjusted_at' => now(),
                ]);

                // Update charge
                $charge->update([
                    'original_amount' => $charge->amount,
                    'amount' => $finalAmount,
                    'adjustment_amount' => $adjustmentAmount,
                ]);

                // Log to audit trail
                Log::channel('stack')->info('Charge adjusted', [
                    'charge_id' => $charge->id,
                    'adjustment_type' => $validated['adjustment_type'],
                    'adjustment_value' => $validated['adjustment_value'],
                    'original_amount' => $charge->original_amount,
                    'adjustment_amount' => $adjustmentAmount,
                    'final_amount' => $finalAmount,
                    'adjusted_by' => auth()->id(),
                    'adjusted_by_name' => auth()->user()->name,
                    'reason' => $validated['reason'],
                    'patient_checkin_id' => $charge->patient_checkin_id,
                    'service_type' => $charge->service_type,
                    'timestamp' => now(),
                ]);
            });

            return back()->with('success', 'Charge adjusted successfully');
        } catch (\Exception $e) {
            Log::error('Charge adjustment failed', [
                'charge_id' => $charge->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to adjust charge. Please try again.',
            ]);
        }
    }
}
