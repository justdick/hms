<?php

/**
 * Property-Based Tests for Password Change
 *
 * These tests verify the correctness properties of password change functionality
 * as defined in the design document.
 */

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

/**
 * Property 5: Password change requires correct current password
 *
 * **Feature: user-management, Property 5: Password change requires correct current password**
 * **Validates: Requirements 5.3**
 *
 * For any password change request, if the provided current password does not match
 * the user's stored password, the system SHALL reject the request.
 */
describe('Property 5: Password change requires correct current password', function () {
    it('rejects password change when current password is incorrect', function () {
        // Arrange
        $correctPassword = 'CorrectPassword123!';
        $user = User::factory()->create([
            'password' => Hash::make($correctPassword),
        ]);

        // Act - Try to change password with wrong current password
        $response = $this->actingAs($user)->put('/settings/password', [
            'current_password' => 'WrongPassword123!',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        // Assert - Request should be rejected with validation error
        $response->assertSessionHasErrors('current_password');

        // Assert - Password should remain unchanged
        $user->refresh();
        expect(Hash::check($correctPassword, $user->password))->toBeTrue();
    });

    it('accepts password change when current password is correct', function () {
        // Arrange
        $correctPassword = 'CorrectPassword123!';
        $newPassword = 'NewSecurePassword123!';
        $user = User::factory()->create([
            'password' => Hash::make($correctPassword),
        ]);

        // Act - Change password with correct current password
        $response = $this->actingAs($user)->put('/settings/password', [
            'current_password' => $correctPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        // Assert - Request should succeed
        $response->assertSessionHasNoErrors();

        // Assert - Password should be updated
        $user->refresh();
        expect(Hash::check($newPassword, $user->password))->toBeTrue();
    });

    it('rejects password change with empty current password', function () {
        // Arrange
        $user = User::factory()->create([
            'password' => Hash::make('CorrectPassword123!'),
        ]);

        // Act
        $response = $this->actingAs($user)->put('/settings/password', [
            'current_password' => '',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        // Assert
        $response->assertSessionHasErrors('current_password');
    });

    it('rejects password change for any randomly generated incorrect password', function (string $wrongPassword) {
        // Arrange
        $correctPassword = 'CorrectPassword123!';
        $user = User::factory()->create([
            'password' => Hash::make($correctPassword),
        ]);

        // Act - Try with various wrong passwords
        $response = $this->actingAs($user)->put('/settings/password', [
            'current_password' => $wrongPassword,
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        // Assert - All should be rejected
        $response->assertSessionHasErrors('current_password');

        // Assert - Original password unchanged
        $user->refresh();
        expect(Hash::check($correctPassword, $user->password))->toBeTrue();
    })->with([
        'wrong1' => 'WrongPassword1!',
        'wrong2' => 'AnotherWrong2@',
        'wrong3' => 'NotCorrect3#',
        'wrong4' => 'Invalid4$',
        'wrong5' => 'BadPassword5%',
        'similar' => 'CorrectPassword123',
        'case_sensitive' => 'correctpassword123!',
        'extra_char' => 'CorrectPassword123!!',
        'missing_char' => 'CorrectPassword12!',
        'spaces' => ' CorrectPassword123! ',
    ]);

    it('validates new password meets complexity requirements', function () {
        // Arrange
        $correctPassword = 'CorrectPassword123!';
        $user = User::factory()->create([
            'password' => Hash::make($correctPassword),
        ]);

        // Act - Try with weak password
        $response = $this->actingAs($user)->put('/settings/password', [
            'current_password' => $correctPassword,
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        // Assert - Should be rejected for complexity
        $response->assertSessionHasErrors('password');

        // Assert - Password unchanged
        $user->refresh();
        expect(Hash::check($correctPassword, $user->password))->toBeTrue();
    });

    it('requires password confirmation to match', function () {
        // Arrange
        $correctPassword = 'CorrectPassword123!';
        $user = User::factory()->create([
            'password' => Hash::make($correctPassword),
        ]);

        // Act - Mismatched confirmation
        $response = $this->actingAs($user)->put('/settings/password', [
            'current_password' => $correctPassword,
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);

        // Assert
        $response->assertSessionHasErrors('password');
    });

    it('rejects various weak passwords', function (string $weakPassword) {
        // Arrange
        $correctPassword = 'CorrectPassword123!';
        $user = User::factory()->create([
            'password' => Hash::make($correctPassword),
        ]);

        // Act
        $response = $this->actingAs($user)->put('/settings/password', [
            'current_password' => $correctPassword,
            'password' => $weakPassword,
            'password_confirmation' => $weakPassword,
        ]);

        // Assert - Should be rejected
        $response->assertSessionHasErrors('password');
    })->with([
        'too_short' => 'Ab1!',
        'no_uppercase' => 'password123!',
        'no_lowercase' => 'PASSWORD123!',
        'no_number' => 'PasswordOnly!',
        'no_symbol' => 'Password123',
        'only_letters' => 'OnlyLettersHere',
        'only_numbers' => '12345678',
    ]);
});

/**
 * Property 6: Password reset sets must_change_password flag
 *
 * **Feature: user-management, Property 6: Password reset sets must_change_password flag**
 * **Validates: Requirements 7.3**
 *
 * For any admin-triggered password reset, the target user's `must_change_password`
 * flag SHALL be set to true.
 */
describe('Property 6: Password reset sets must_change_password flag', function () {
    beforeEach(function () {
        // Create the permission needed for password reset
        Permission::firstOrCreate(['name' => 'users.reset-password', 'guard_name' => 'web']);
    });

    it('sets must_change_password flag when admin resets password', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->givePermissionTo('users.reset-password');

        $targetUser = User::factory()->create([
            'must_change_password' => false,
        ]);

        // Act - Admin resets user's password
        $response = $this->actingAs($admin)->post("/admin/users/{$targetUser->id}/reset-password");

        // Assert - must_change_password flag should be set
        $targetUser->refresh();
        expect($targetUser->must_change_password)->toBeTrue();
    });

    it('sets must_change_password flag even if already true', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->givePermissionTo('users.reset-password');

        $targetUser = User::factory()->create([
            'must_change_password' => true,
        ]);

        // Act
        $response = $this->actingAs($admin)->post("/admin/users/{$targetUser->id}/reset-password");

        // Assert - Flag should still be true
        $targetUser->refresh();
        expect($targetUser->must_change_password)->toBeTrue();
    });

    it('generates a temporary password on reset', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->givePermissionTo('users.reset-password');

        $targetUser = User::factory()->create();
        $oldPasswordHash = $targetUser->password;

        // Act
        $response = $this->actingAs($admin)->post("/admin/users/{$targetUser->id}/reset-password");

        // Assert - Password should be changed
        $targetUser->refresh();
        expect($targetUser->password)->not->toBe($oldPasswordHash);

        // Assert - Temporary password is returned in session
        $response->assertSessionHas('temporary_password');
    });

    it('clears must_change_password flag after user changes password', function () {
        // Arrange
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
            'must_change_password' => true,
        ]);

        // Act - User changes their password
        $response = $this->actingAs($user)->put('/settings/password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        // Assert - must_change_password should be cleared
        $user->refresh();
        expect($user->must_change_password)->toBeFalse();
    });

    it('sets must_change_password for any user regardless of their current state', function () {
        // Arrange
        $admin = User::factory()->create();
        $admin->givePermissionTo('users.reset-password');

        // Create users with different states
        $activeUser = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $inactiveUser = User::factory()->create(['is_active' => false, 'must_change_password' => false]);
        $alreadyFlaggedUser = User::factory()->create(['is_active' => true, 'must_change_password' => true]);

        // Act & Assert for each user
        $this->actingAs($admin)->post("/admin/users/{$activeUser->id}/reset-password");
        $activeUser->refresh();
        expect($activeUser->must_change_password)->toBeTrue();

        $this->actingAs($admin)->post("/admin/users/{$inactiveUser->id}/reset-password");
        $inactiveUser->refresh();
        expect($inactiveUser->must_change_password)->toBeTrue();

        $this->actingAs($admin)->post("/admin/users/{$alreadyFlaggedUser->id}/reset-password");
        $alreadyFlaggedUser->refresh();
        expect($alreadyFlaggedUser->must_change_password)->toBeTrue();
    });
});

/**
 * Tests for EnsurePasswordChanged middleware
 *
 * **Feature: user-management, Property 6: Password reset sets must_change_password flag**
 * **Validates: Requirements 7.3**
 *
 * When a user logs in with a temporary password, the system SHALL require
 * immediate password change before accessing other features.
 */
describe('EnsurePasswordChanged middleware', function () {
    it('redirects user with must_change_password to password change page', function () {
        // Arrange
        $user = User::factory()->create([
            'must_change_password' => true,
        ]);

        // Act - Try to access dashboard
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - Should redirect to password change page
        $response->assertRedirect(route('password.edit'));
    });

    it('allows user with must_change_password to access password change page', function () {
        // Arrange
        $user = User::factory()->create([
            'must_change_password' => true,
        ]);

        // Act - Access password change page
        $response = $this->actingAs($user)->get('/settings/password');

        // Assert - Should be allowed
        $response->assertOk();
    });

    it('allows user with must_change_password to submit password change', function () {
        // Arrange
        $user = User::factory()->create([
            'password' => Hash::make('TempPassword123!'),
            'must_change_password' => true,
        ]);

        // Act - Submit password change
        $response = $this->actingAs($user)->put('/settings/password', [
            'current_password' => 'TempPassword123!',
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        // Assert - Should succeed
        $response->assertSessionHasNoErrors();
        $user->refresh();
        expect($user->must_change_password)->toBeFalse();
    });

    it('allows user with must_change_password to logout', function () {
        // Arrange
        $user = User::factory()->create([
            'must_change_password' => true,
        ]);

        // Act - Logout
        $response = $this->actingAs($user)->post('/logout');

        // Assert - Should be allowed (redirects to home after logout)
        $response->assertRedirect('/');
    });

    it('allows normal user to access any page', function () {
        // Arrange
        $user = User::factory()->create([
            'must_change_password' => false,
        ]);

        // Act - Access dashboard
        $response = $this->actingAs($user)->get('/dashboard');

        // Assert - Should be allowed
        $response->assertOk();
    });

    it('redirects user with must_change_password from any protected route', function (string $route) {
        // Arrange
        $user = User::factory()->create([
            'must_change_password' => true,
        ]);

        // Act - Try to access various routes
        $response = $this->actingAs($user)->get($route);

        // Assert - Should redirect to password change page
        $response->assertRedirect(route('password.edit'));
    })->with([
        'dashboard' => '/dashboard',
        'settings_profile' => '/settings/profile',
        'settings_appearance' => '/settings/appearance',
    ]);
});
