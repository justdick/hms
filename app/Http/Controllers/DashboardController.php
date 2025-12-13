<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\AdminDashboard;
use App\Services\Dashboard\CashierDashboard;
use App\Services\Dashboard\DashboardService;
use App\Services\Dashboard\DoctorDashboard;
use App\Services\Dashboard\FinanceDashboard;
use App\Services\Dashboard\InsuranceDashboard;
use App\Services\Dashboard\NurseDashboard;
use App\Services\Dashboard\PharmacistDashboard;
use App\Services\Dashboard\ReceptionistDashboard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the main dashboard with role-based data.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $dashboardService = new DashboardService($user);

        // Register all dashboard widgets
        $dashboardService
            ->registerWidget(new ReceptionistDashboard)
            ->registerWidget(new DoctorDashboard)
            ->registerWidget(new PharmacistDashboard)
            ->registerWidget(new NurseDashboard)
            ->registerWidget(new CashierDashboard)
            ->registerWidget(new FinanceDashboard)
            ->registerWidget(new InsuranceDashboard)
            ->registerWidget(new AdminDashboard);

        // Get visible widgets and quick actions (lightweight, always loaded)
        $visibleWidgets = $dashboardService->getVisibleWidgets();
        $quickActions = $dashboardService->getQuickActions();

        return Inertia::render('Dashboard/Index', [
            // Always loaded - lightweight data
            'visibleWidgets' => $visibleWidgets,
            'quickActions' => $quickActions,
            'userRoles' => $user->getRoleNames()->toArray(),
            'userPermissions' => $user->getAllPermissions()->pluck('name')->toArray(),

            // Deferred props - heavier data loaded after initial render
            'metrics' => Inertia::defer(fn () => $dashboardService->getMetrics()),
            'lists' => Inertia::defer(fn () => $dashboardService->getLists()),
        ]);
    }
}
