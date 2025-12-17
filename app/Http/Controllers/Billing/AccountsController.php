<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\PaymentMethod;
use App\Services\CollectionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountsController extends Controller
{
    public function __construct(
        private CollectionService $collectionService
    ) {}

    /**
     * Display the finance officer dashboard.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAll', Charge::class);

        // Parse date range from request, default to today
        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))
            : today();
        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))
            : today();

        // Get total collections for the date range
        $totalCollections = Charge::whereBetween('paid_at', [$startDate->startOfDay(), $endDate->copy()->endOfDay()])
            ->whereIn('status', ['paid', 'partial'])
            ->sum('paid_amount');

        $transactionCount = Charge::whereBetween('paid_at', [$startDate->startOfDay(), $endDate->copy()->endOfDay()])
            ->whereIn('status', ['paid', 'partial'])
            ->count();

        // Get collections by cashier
        $collectionsByCashier = $this->collectionService->getCollectionsByCashier($startDate, $endDate);

        // Get collections by payment method
        $collectionsByPaymentMethod = $this->getCollectionsByPaymentMethod($startDate, $endDate);

        // Get collections by department
        $collectionsByDepartment = $this->collectionService->getCollectionsByDepartment($startDate, $endDate);

        // Get payment methods for filter
        $paymentMethods = PaymentMethod::active()->get(['id', 'name', 'code']);

        return Inertia::render('Billing/Accounts/Index', [
            'totalCollections' => (float) $totalCollections,
            'transactionCount' => $transactionCount,
            'collectionsByCashier' => $collectionsByCashier,
            'collectionsByPaymentMethod' => $collectionsByPaymentMethod,
            'collectionsByDepartment' => $collectionsByDepartment,
            'paymentMethods' => $paymentMethods,
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
        ]);
    }

    /**
     * Get collections breakdown by payment method for a date range.
     */
    private function getCollectionsByPaymentMethod(Carbon $startDate, Carbon $endDate): array
    {
        $collections = Charge::whereBetween('paid_at', [$startDate->startOfDay(), $endDate->copy()->endOfDay()])
            ->whereIn('status', ['paid', 'partial'])
            ->selectRaw("
                COALESCE(metadata->>'payment_method', 'cash') as payment_method,
                SUM(paid_amount) as total_amount,
                COUNT(*) as transaction_count
            ")
            ->groupBy('payment_method')
            ->get();

        // Get all payment methods for complete breakdown
        $paymentMethods = PaymentMethod::active()->get();

        $breakdown = [];
        foreach ($paymentMethods as $method) {
            $collection = $collections->firstWhere('payment_method', $method->code);
            $breakdown[] = [
                'name' => $method->name,
                'code' => $method->code,
                'total_amount' => $collection ? (float) $collection->total_amount : 0,
                'transaction_count' => $collection ? (int) $collection->transaction_count : 0,
            ];
        }

        return $breakdown;
    }
}
