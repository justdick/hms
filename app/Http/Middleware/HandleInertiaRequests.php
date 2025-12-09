<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                'permissions' => [
                    'pharmacy' => [
                        'inventory' => $request->user()?->can('inventory.view') ?? false,
                        'dispensing' => $request->user()?->can('dispensing.view') ?? false,
                    ],
                    'admissions' => [
                        'discharge' => $request->user()?->can('admissions.discharge') ?? false,
                    ],
                    'billing' => [
                        'viewAll' => $request->user()?->can('billing.view-all') ?? false,
                        'collect' => $request->user()?->can('billing.collect') ?? false,
                        'override' => $request->user()?->can('billing.override') ?? false,
                        'reconcile' => $request->user()?->can('billing.reconcile') ?? false,
                        'reports' => $request->user()?->can('billing.reports') ?? false,
                        'statements' => $request->user()?->can('billing.statements') ?? false,
                        'manageCredit' => $request->user()?->can('billing.manage-credit') ?? false,
                        'void' => $request->user()?->can('billing.void') ?? false,
                        'refund' => $request->user()?->can('billing.refund') ?? false,
                        'configure' => $request->user()?->can('billing.configure') ?? false,
                    ],
                    'backups' => [
                        'view' => $request->user()?->can('backups.view') ?? false,
                        'create' => $request->user()?->can('backups.create') ?? false,
                        'delete' => $request->user()?->can('backups.delete') ?? false,
                        'restore' => $request->user()?->can('backups.restore') ?? false,
                        'manageSettings' => $request->user()?->can('backups.manage-settings') ?? false,
                    ],
                    'users' => [
                        'viewAll' => $request->user()?->can('users.view-all') ?? false,
                        'create' => $request->user()?->can('users.create') ?? false,
                        'update' => $request->user()?->can('users.update') ?? false,
                        'resetPassword' => $request->user()?->can('users.reset-password') ?? false,
                    ],
                    'roles' => [
                        'viewAll' => $request->user()?->can('roles.view-all') ?? false,
                        'create' => $request->user()?->can('roles.create') ?? false,
                        'update' => $request->user()?->can('roles.update') ?? false,
                        'delete' => $request->user()?->can('roles.delete') ?? false,
                    ],
                ],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
                'temporary_password' => $request->session()->get('temporary_password'),
            ],
            'patient' => $request->session()->get('patient'),
        ];
    }
}
