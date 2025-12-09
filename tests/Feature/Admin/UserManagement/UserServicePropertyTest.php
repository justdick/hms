<?php

/**
 * Property-Based Tests for UserService
 *
 * These tests verify the correctness properties of the user service
 * as defined in the design document.
 */

use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    // Clean up sessions table before each test
    DB::table('sessions')->truncate();
});

/**
 * Property 8: Successful password change invalidates other sessions
 *
 * **Feature: user-management, Property 8: Successful password change invalidates other sessions**
 * **Validates: Requirements 5.5, 7.4**
 *
 * For any successful password change (self-service or reset), all existing sessions
 * for that user except the current one SHALL be invalidated.
 */
describe('Property 8: Successful password change invalidates other sessions', function () {
    it('invalidates all other sessions when password is changed', function () {
        // Arrange
        $user = User::factory()->create();
        $service = new UserService;

        // Create multiple sessions for the user
        $currentSessionId = 'current-session-id-'.uniqid();
        $otherSessionIds = [
            'other-session-1-'.uniqid(),
            'other-session-2-'.uniqid(),
            'other-session-3-'.uniqid(),
        ];

        // Insert current session
        DB::table('sessions')->insert([
            'id' => $currentSessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Browser',
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);

        // Insert other sessions
        foreach ($otherSessionIds as $sessionId) {
            DB::table('sessions')->insert([
                'id' => $sessionId,
                'user_id' => $user->id,
                'ip_address' => '192.168.1.'.rand(1, 255),
                'user_agent' => 'Other Browser',
                'payload' => base64_encode(serialize([])),
                'last_activity' => time(),
            ]);
        }

        // Verify all sessions exist
        expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(4);

        // Act - Change password with current session ID
        $service->changePassword($user, 'NewSecurePassword123!', $currentSessionId);

        // Assert - Only current session should remain
        $remainingSessions = DB::table('sessions')->where('user_id', $user->id)->get();
        expect($remainingSessions)->toHaveCount(1);
        expect($remainingSessions->first()->id)->toBe($currentSessionId);
    });

    it('invalidates all sessions when password is reset by admin', function () {
        // Arrange
        $user = User::factory()->create();
        $service = new UserService;

        // Create multiple sessions for the user
        $sessionIds = [
            'session-1-'.uniqid(),
            'session-2-'.uniqid(),
            'session-3-'.uniqid(),
        ];

        foreach ($sessionIds as $sessionId) {
            DB::table('sessions')->insert([
                'id' => $sessionId,
                'user_id' => $user->id,
                'ip_address' => '192.168.1.'.rand(1, 255),
                'user_agent' => 'Test Browser',
                'payload' => base64_encode(serialize([])),
                'last_activity' => time(),
            ]);
        }

        // Verify sessions exist
        expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(3);

        // Act - Reset password (admin action, no current session to preserve)
        $temporaryPassword = $service->resetPassword($user);

        // Assert - All sessions should be invalidated
        expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);

        // Assert - Temporary password was generated
        expect($temporaryPassword)->toBeString();
        expect(strlen($temporaryPassword))->toBeGreaterThanOrEqual(8);

        // Assert - must_change_password flag is set
        $user->refresh();
        expect($user->must_change_password)->toBeTrue();
    });

    it('does not affect other users sessions', function () {
        // Arrange
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $service = new UserService;

        // Create sessions for both users
        DB::table('sessions')->insert([
            'id' => 'user1-session-'.uniqid(),
            'user_id' => $user1->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Browser',
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);

        DB::table('sessions')->insert([
            'id' => 'user2-session-'.uniqid(),
            'user_id' => $user2->id,
            'ip_address' => '127.0.0.2',
            'user_agent' => 'Test Browser',
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);

        // Verify both users have sessions
        expect(DB::table('sessions')->where('user_id', $user1->id)->count())->toBe(1);
        expect(DB::table('sessions')->where('user_id', $user2->id)->count())->toBe(1);

        // Act - Reset password for user1
        $service->resetPassword($user1);

        // Assert - User1's sessions are invalidated, User2's remain
        expect(DB::table('sessions')->where('user_id', $user1->id)->count())->toBe(0);
        expect(DB::table('sessions')->where('user_id', $user2->id)->count())->toBe(1);
    });

    it('handles user with no existing sessions gracefully', function () {
        // Arrange
        $user = User::factory()->create();
        $service = new UserService;

        // Verify no sessions exist
        expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);

        // Act - Should not throw exception
        $temporaryPassword = $service->resetPassword($user);

        // Assert
        expect($temporaryPassword)->toBeString();
        expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
    });

    it('invalidates sessions for any number of existing sessions', function (int $sessionCount) {
        // Arrange
        $user = User::factory()->create();
        $service = new UserService;

        // Create specified number of sessions
        for ($i = 0; $i < $sessionCount; $i++) {
            DB::table('sessions')->insert([
                'id' => 'session-'.$i.'-'.uniqid(),
                'user_id' => $user->id,
                'ip_address' => '192.168.1.'.($i % 255 + 1),
                'user_agent' => 'Browser '.$i,
                'payload' => base64_encode(serialize([])),
                'last_activity' => time(),
            ]);
        }

        // Verify sessions exist
        expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe($sessionCount);

        // Act
        $service->resetPassword($user);

        // Assert - All sessions should be invalidated
        expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
    })->with([1, 2, 5, 10, 20]);

    it('clears must_change_password flag after self-service password change', function () {
        // Arrange
        $user = User::factory()->create(['must_change_password' => true]);
        $service = new UserService;
        $currentSessionId = 'current-session-'.uniqid();

        DB::table('sessions')->insert([
            'id' => $currentSessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Browser',
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);

        // Act
        $service->changePassword($user, 'NewSecurePassword123!', $currentSessionId);

        // Assert
        $user->refresh();
        expect($user->must_change_password)->toBeFalse();
    });

    it('updates password hash correctly', function () {
        // Arrange
        $user = User::factory()->create();
        $service = new UserService;
        $newPassword = 'NewSecurePassword123!';
        $oldPasswordHash = $user->password;

        // Act
        $service->changePassword($user, $newPassword, null);

        // Assert
        $user->refresh();
        expect($user->password)->not->toBe($oldPasswordHash);
        expect(Hash::check($newPassword, $user->password))->toBeTrue();
    });
});
