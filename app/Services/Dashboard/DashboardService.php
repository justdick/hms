<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Orchestrates role-based dashboard data.
 *
 * This service determines which widgets are visible based on user permissions,
 * aggregates metrics from visible widgets, and provides filtered quick actions.
 */
class DashboardService
{
    /**
     * Widget permission mapping.
     * Maps widget IDs to required permissions (user needs at least one).
     *
     * @var array<string, array<string>>
     */
    private const WIDGET_PERMISSIONS = [
        // Check-in widgets
        'checkin_metrics' => ['checkins.view-all', 'checkins.view-dept'],
        'recent_checkins' => ['checkins.view-all', 'checkins.view-dept'],

        // Vitals widgets
        'vitals_queue' => ['vitals.view-all', 'vitals.view-dept'],

        // Consultation widgets
        'consultation_queue' => ['consultations.view-all', 'consultations.view-dept'],
        'active_consultations' => ['consultations.view-all', 'consultations.view-dept', 'consultations.view-own'],

        // Prescription/Dispensing widgets
        'prescription_queue' => ['prescriptions.view', 'dispensing.view'],
        'dispensing_metrics' => ['dispensing.process', 'dispensing.review'],

        // Inventory widgets
        'low_stock_alerts' => ['inventory.view'],

        // Billing widgets
        'billing_metrics' => ['billing.view-all', 'billing.collect'],
        'recent_payments' => ['billing.view-all', 'billing.collect'],

        // Finance widgets
        'revenue_summary' => ['billing.reports', 'billing.reconcile'],
        'finance_metrics' => ['billing.reports'],

        // Insurance widgets
        'claims_metrics' => ['insurance.view', 'insurance.vet-claims'],
        'claims_queue' => ['insurance.vet-claims'],

        // Lab widgets
        'lab_queue' => ['lab-orders.view-all', 'lab-orders.view-dept'],
        'pending_lab_results' => ['lab-orders.view-all', 'lab-orders.view-dept'],

        // Ward/Admission widgets
        'ward_metrics' => ['admissions.view'],
        'active_admissions' => ['admissions.view'],

        // Medication administration
        'medication_schedule' => ['view medication administrations', 'administer medications'],

        // Admin widgets
        'system_overview' => ['system.admin'],
        'admin_metrics' => ['system.admin'],
    ];

    /**
     * Quick action permission mapping.
     * Maps action keys to required permission.
     *
     * @var array<string, string>
     */
    private const QUICK_ACTION_PERMISSIONS = [
        'register_patient' => 'patients.create',
        'checkin_patient' => 'checkins.create',
        'record_vitals' => 'vitals.create',
        'start_consultation' => 'consultations.create',
        'view_ward_patients' => 'admissions.view',
        'admit_patient' => 'admissions.create',
        'review_prescriptions' => 'dispensing.review',
        'process_dispensing' => 'dispensing.process',
        'view_inventory' => 'inventory.view',
        'process_payment' => 'billing.collect',
        'view_reports' => 'billing.reports',
        'cash_reconciliation' => 'billing.reconcile',
        'vet_claims' => 'insurance.vet-claims',
        'create_batch' => 'insurance.manage-batches',
        'manage_users' => 'users.view-all',
        'system_settings' => 'system.settings',
    ];

    /**
     * Quick action definitions with labels, routes, icons, and variants.
     *
     * Variants: primary (blue), accent (teal), success (green), warning (amber), info (blue), danger (red)
     *
     * @var array<string, array{label: string, route: string, icon: string, variant: string}>
     */
    private const QUICK_ACTIONS = [
        'register_patient' => [
            'label' => 'Register Patient',
            'route' => 'patients.create',
            'icon' => 'UserPlus',
            'variant' => 'primary',
        ],
        'checkin_patient' => [
            'label' => 'Check-in Patient',
            'route' => 'checkin.index',
            'icon' => 'ClipboardCheck',
            'variant' => 'primary',
        ],
        'record_vitals' => [
            'label' => 'Record Vitals',
            'route' => 'checkin.index',
            'icon' => 'Activity',
            'variant' => 'accent',
        ],
        'start_consultation' => [
            'label' => 'Start Consultation',
            'route' => 'consultation.index',
            'icon' => 'Stethoscope',
            'variant' => 'accent',
        ],
        'view_ward_patients' => [
            'label' => 'View Ward Patients',
            'route' => 'wards.index',
            'icon' => 'Bed',
            'variant' => 'info',
        ],
        'admit_patient' => [
            'label' => 'Admit Patient',
            'route' => 'admissions.create',
            'icon' => 'Building2',
            'variant' => 'warning',
        ],
        'review_prescriptions' => [
            'label' => 'Review Prescriptions',
            'route' => 'pharmacy.review',
            'icon' => 'FileCheck',
            'variant' => 'warning',
        ],
        'process_dispensing' => [
            'label' => 'Process Dispensing',
            'route' => 'pharmacy.dispensing.index',
            'icon' => 'Pill',
            'variant' => 'accent',
        ],
        'view_inventory' => [
            'label' => 'View Inventory',
            'route' => 'pharmacy.drugs.index',
            'icon' => 'Package',
            'variant' => 'info',
        ],
        'process_payment' => [
            'label' => 'Process Payment',
            'route' => 'billing.index',
            'icon' => 'CreditCard',
            'variant' => 'success',
        ],
        'view_reports' => [
            'label' => 'View Reports',
            'route' => 'billing.reports.index',
            'icon' => 'BarChart3',
            'variant' => 'info',
        ],
        'cash_reconciliation' => [
            'label' => 'Cash Reconciliation',
            'route' => 'billing.reconciliation.index',
            'icon' => 'Calculator',
            'variant' => 'success',
        ],
        'vet_claims' => [
            'label' => 'Vet Claims',
            'route' => 'insurance.claims.vetting',
            'icon' => 'FileSearch',
            'variant' => 'warning',
        ],
        'create_batch' => [
            'label' => 'Create Batch',
            'route' => 'insurance.batches.create',
            'icon' => 'FolderPlus',
            'variant' => 'primary',
        ],
        'manage_users' => [
            'label' => 'Manage Users',
            'route' => 'admin.users.index',
            'icon' => 'Users',
            'variant' => 'primary',
        ],
        'system_settings' => [
            'label' => 'System Settings',
            'route' => 'settings.index',
            'icon' => 'Settings',
            'variant' => 'default',
        ],
    ];

    protected User $user;

    /** @var Collection<int, DashboardWidgetInterface> */
    protected Collection $widgets;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->widgets = collect();
    }

    /**
     * Register a widget service.
     */
    public function registerWidget(DashboardWidgetInterface $widget): self
    {
        $this->widgets->push($widget);

        return $this;
    }

    /**
     * Get visible widget IDs based on user permissions.
     *
     * @return array<string>
     */
    public function getVisibleWidgets(): array
    {
        $visibleWidgets = [];

        foreach (self::WIDGET_PERMISSIONS as $widgetId => $permissions) {
            if ($this->userHasAnyPermission($permissions)) {
                $visibleWidgets[] = $widgetId;
            }
        }

        return $visibleWidgets;
    }

    /**
     * Get metrics only for visible widgets.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        $metrics = [];
        $visibleWidgets = $this->getVisibleWidgets();

        foreach ($this->widgets as $widget) {
            if (in_array($widget->getWidgetId(), $visibleWidgets, true) && $widget->canView($this->user)) {
                $metrics = array_merge($metrics, $widget->getMetrics($this->user));
            }
        }

        return $metrics;
    }

    /**
     * Get list data for visible widgets.
     *
     * @return array<string, mixed>
     */
    public function getLists(): array
    {
        $lists = [];
        $visibleWidgets = $this->getVisibleWidgets();

        foreach ($this->widgets as $widget) {
            if (in_array($widget->getWidgetId(), $visibleWidgets, true) && $widget->canView($this->user)) {
                $lists = array_merge($lists, $widget->getListData($this->user));
            }
        }

        return $lists;
    }

    /**
     * Get quick actions filtered by user permissions.
     *
     * @return array<int, array{key: string, label: string, route: string, icon: string, href: string|null, variant: string}>
     */
    public function getQuickActions(): array
    {
        $actions = [];

        foreach (self::QUICK_ACTION_PERMISSIONS as $actionKey => $permission) {
            if ($this->user->can($permission)) {
                $actionDef = self::QUICK_ACTIONS[$actionKey];
                $actions[] = [
                    'key' => $actionKey,
                    'label' => $actionDef['label'],
                    'route' => $actionDef['route'],
                    'icon' => $actionDef['icon'],
                    'href' => $this->resolveRoute($actionDef['route']),
                    'variant' => $actionDef['variant'],
                ];
            }
        }

        return $actions;
    }

    /**
     * Get all dashboard data for the user.
     *
     * @return array{
     *     visibleWidgets: array<string>,
     *     metrics: array<string, mixed>,
     *     lists: array<string, mixed>,
     *     quickActions: array<int, array{key: string, label: string, route: string, icon: string, href: string|null, variant: string}>
     * }
     */
    public function getDashboardData(): array
    {
        return [
            'visibleWidgets' => $this->getVisibleWidgets(),
            'metrics' => $this->getMetrics(),
            'lists' => $this->getLists(),
            'quickActions' => $this->getQuickActions(),
        ];
    }

    /**
     * Check if user has any of the given permissions.
     *
     * @param  array<string>  $permissions
     */
    protected function userHasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve a route name to a URL, returning null if route doesn't exist.
     */
    protected function resolveRoute(string $routeName): ?string
    {
        try {
            return route($routeName);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Get the user's departments for filtering.
     *
     * @return array<int>
     */
    public function getUserDepartmentIds(): array
    {
        return $this->user->departments->pluck('id')->toArray();
    }

    /**
     * Check if user has access to all data (admin-level).
     */
    public function hasFullAccess(): bool
    {
        return $this->user->hasRole('Admin') || $this->user->can('system.admin');
    }
}
