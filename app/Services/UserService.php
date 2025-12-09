<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserService
{
    /**
     * Generate a secure temporary password.
     */
    public function generateTemporaryPassword(): string
    {
        // Generate a 12-character password with mixed case, numbers, and symbols
        return Str::password(12);
    }

    /**
     * Invalidate all sessions for a user except the current one.
     */
    public function invalidateUserSessions(User $user, ?string $exceptSessionId = null): int
    {
        $query = DB::table('sessions')->where('user_id', $user->id);

        if ($exceptSessionId) {
            $query->where('id', '!=', $exceptSessionId);
        }

        return $query->delete();
    }

    /**
     * Create a new user with the given data.
     */
    public function createUser(array $data): array
    {
        $temporaryPassword = $this->generateTemporaryPassword();

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'password' => Hash::make($temporaryPassword),
            'is_active' => $data['is_active'] ?? true,
            'must_change_password' => true,
        ]);

        // Sync roles
        if (isset($data['roles']) && is_array($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        // Sync departments
        if (isset($data['departments']) && is_array($data['departments'])) {
            $user->departments()->sync($data['departments']);
        }

        return [
            'user' => $user,
            'temporary_password' => $temporaryPassword,
        ];
    }

    /**
     * Update an existing user with the given data.
     */
    public function updateUser(User $user, array $data): User
    {
        $user->update([
            'name' => $data['name'] ?? $user->name,
            'username' => $data['username'] ?? $user->username,
        ]);

        // Sync roles if provided
        if (isset($data['roles']) && is_array($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        // Sync departments if provided
        if (isset($data['departments']) && is_array($data['departments'])) {
            $user->departments()->sync($data['departments']);
        }

        return $user->fresh();
    }

    /**
     * Reset a user's password and set must_change_password flag.
     */
    public function resetPassword(User $user): string
    {
        $temporaryPassword = $this->generateTemporaryPassword();

        $user->update([
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
        ]);

        // Invalidate all existing sessions for security
        $this->invalidateUserSessions($user);

        return $temporaryPassword;
    }

    /**
     * Change a user's password and invalidate other sessions.
     */
    public function changePassword(User $user, string $newPassword, ?string $currentSessionId = null): void
    {
        $user->update([
            'password' => Hash::make($newPassword),
            'must_change_password' => false,
        ]);

        // Invalidate all other sessions except the current one
        $this->invalidateUserSessions($user, $currentSessionId);
    }

    /**
     * Toggle a user's active status.
     */
    public function toggleActive(User $user): User
    {
        $user->update([
            'is_active' => ! $user->is_active,
        ]);

        // If deactivating, invalidate all sessions
        if (! $user->is_active) {
            $this->invalidateUserSessions($user);
        }

        return $user->fresh();
    }
}
