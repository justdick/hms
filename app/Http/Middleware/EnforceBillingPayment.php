<?php

namespace App\Http\Middleware;

use App\Services\BillingService;
use Closure;
use Illuminate\Http\Request;

class EnforceBillingPayment
{
    public function __construct(
        private BillingService $billingService
    ) {}

    public function handle(Request $request, Closure $next, string $serviceType, ?string $serviceCode = null)
    {
        // Try to get checkin ID from route parameter or request input
        $checkinId = $request->route('checkin') ?? $request->input('patient_checkin_id');

        // If not found, try to get it from labOrder route parameter
        if (! $checkinId && $request->route('labOrder')) {
            $labOrder = $request->route('labOrder');
            $checkinId = $labOrder->consultation?->patient_checkin_id;
        }

        if (! $checkinId) {
            // Continue with request but add notification about missing checkin ID
            session()->flash('warning', 'Patient check-in ID is required for billing enforcement');

            return $next($request);
        }

        $checkin = \App\Models\PatientCheckin::find($checkinId);

        if (! $checkin) {
            // Continue with request but add notification
            session()->flash('warning', 'Patient check-in not found for billing verification');

            return $next($request);
        }

        if (! $this->billingService->canProceedWithService($checkin, $serviceType, $serviceCode)) {
            $pendingCharges = $this->billingService->getPendingCharges($checkin, $serviceType);
            $totalAmount = $pendingCharges->sum('amount');

            // Block the request and redirect back with error
            return redirect()->back()->with('error', "Payment Required: Cannot proceed with {$serviceType}. Payment of ₹{$totalAmount} is required before accessing this service.");
        }

        return $next($request);
    }

    private function isEmergencyOverrideAvailable(string $serviceType, ?string $serviceCode = null): bool
    {
        $rule = \App\Models\ServiceChargeRule::where('service_type', $serviceType)
            ->where(function ($query) use ($serviceCode) {
                $query->whereNull('service_code')
                    ->orWhere('service_code', $serviceCode);
            })
            ->where('is_active', true)
            ->first();

        return $rule?->emergency_override_allowed ?? false;
    }
}
