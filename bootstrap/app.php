<?php

use App\Http\Middleware\EnforceBillingPayment;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HideLabTestDetails;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/patients.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Append EnsurePasswordChanged to the auth middleware group
        $middleware->appendToGroup('auth', [
            EnsurePasswordChanged::class,
        ]);

        $middleware->alias([
            'billing.enforce' => EnforceBillingPayment::class,
            'lab.hide_details' => HideLabTestDetails::class,
            'password.changed' => EnsurePasswordChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle session expiration - redirect unauthenticated users to login
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            // Session expired or CSRF mismatch
            if (in_array($status, [403, 419])) {
                // If user is not authenticated, redirect to login
                if (! $request->user()) {
                    return redirect()->route('login')
                        ->with('error', 'Your session has expired. Please log in again.');
                }
            }

            return $response;
        });
    })->create();
