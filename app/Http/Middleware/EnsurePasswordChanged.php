<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Routes that are allowed even when password change is required.
     *
     * @var array<string>
     */
    protected array $allowedRoutes = [
        'password.edit',
        'password.update',
        'logout',
    ];

    /**
     * Handle an incoming request.
     *
     * Redirects users with must_change_password flag to the password change page.
     * Only allows access to password change and logout routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Skip if not authenticated
        if (! $user) {
            return $next($request);
        }

        // Skip if user doesn't need to change password
        if (! $user->must_change_password) {
            return $next($request);
        }

        // Allow access to permitted routes
        $currentRoute = $request->route()?->getName();
        if ($currentRoute && in_array($currentRoute, $this->allowedRoutes)) {
            return $next($request);
        }

        // Redirect to password change page with a message
        return redirect()->route('password.edit')
            ->with('warning', 'You must change your password before continuing.');
    }
}
