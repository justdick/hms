<?php

namespace App\Http\Middleware;

use App\Services\BillingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HideLabTestDetails
{
    public function __construct(
        private BillingService $billingService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        $content = $response->getContent();
        $data = json_decode($content, true);

        if (! $data || ! isset($data['lab_orders'])) {
            return $response;
        }

        foreach ($data['lab_orders'] as &$labOrder) {
            $checkin = \App\Models\PatientCheckin::find($labOrder['patient_checkin_id']);

            if (! $checkin) {
                continue;
            }

            $canViewDetails = $this->billingService->canProceedWithService($checkin, 'laboratory');

            if (! $canViewDetails) {
                $labOrder['test_details_hidden'] = true;
                $labOrder['payment_required_message'] = 'Payment required to view test details';

                if (isset($labOrder['lab_order_items'])) {
                    foreach ($labOrder['lab_order_items'] as &$item) {
                        $item['lab_service']['parameters'] = null;
                        $item['lab_service']['instructions'] = null;
                        $item['lab_service']['normal_ranges'] = null;
                        $item['result'] = null;
                        $item['status'] = 'payment_required';
                    }
                }
            }
        }

        return response()->json($data);
    }
}
