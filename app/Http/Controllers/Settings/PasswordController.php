<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePasswordRequest;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    public function __construct(protected UserService $userService) {}

    /**
     * Show the user's password settings page.
     */
    public function edit(): Response
    {
        $user = auth()->user();

        return Inertia::render('settings/password', [
            'mustChangePassword' => $user->must_change_password,
        ]);
    }

    /**
     * Update the user's password.
     *
     * Validates current password, updates to new password,
     * and invalidates all other sessions for security.
     */
    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $currentSessionId = $request->session()->getId();
        $wasForced = $user->must_change_password;

        // Use UserService to change password and invalidate other sessions
        $this->userService->changePassword(
            $user,
            $request->validated('password'),
            $currentSessionId
        );

        // If user was forced to change password, redirect to dashboard
        if ($wasForced) {
            return redirect()
                ->route('dashboard')
                ->with('success', 'Password updated successfully. Welcome!');
        }

        return back()->with('success', 'Password updated successfully.');
    }
}
