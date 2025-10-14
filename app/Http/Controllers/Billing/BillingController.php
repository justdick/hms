<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function index(): Response
    {
        // For now, redirect to the charges management system
        return redirect()->route('billing.charges.index');
    }

    public function dashboard(): Response
    {
        $stats = [
            'pending_charges' => Charge::pending()->count(),
            'pending_amount' => Charge::pending()->sum('amount'),
            'paid_today' => Charge::whereDate('paid_at', today())
                ->whereIn('status', ['paid', 'partial'])
                ->sum('paid_amount'),
            'total_outstanding' => Charge::pending()->sum('amount'),
        ];

        $recentCharges = Charge::with([
            'patientCheckin.patient:id,first_name,last_name,phone_number',
            'patientCheckin.department:id,name',
        ])
            ->latest('charged_at')
            ->limit(10)
            ->get();

        $serviceTypeStats = Charge::select('service_type')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(amount) as total_amount')
            ->selectRaw('SUM(paid_amount) as paid_amount')
            ->groupBy('service_type')
            ->get();

        $departmentStats = DB::table('charges')
            ->join('patient_checkins', 'charges.patient_checkin_id', '=', 'patient_checkins.id')
            ->join('departments', 'patient_checkins.department_id', '=', 'departments.id')
            ->select('departments.name as department_name')
            ->selectRaw('COUNT(charges.id) as charge_count')
            ->selectRaw('SUM(charges.amount) as total_revenue')
            ->selectRaw('SUM(CASE WHEN charges.status = "pending" THEN charges.amount ELSE 0 END) as pending_amount')
            ->groupBy('departments.id', 'departments.name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        return Inertia::render('Billing/Dashboard', [
            'stats' => $stats,
            'recentCharges' => $recentCharges,
            'serviceTypeStats' => $serviceTypeStats,
            'departmentStats' => $departmentStats,
        ]);
    }
}
