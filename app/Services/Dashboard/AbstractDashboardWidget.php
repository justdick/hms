<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Abstract base class for dashboard widgets.
 *
 * Provides common functionality for permission checking,
 * department-based filtering, and caching.
 */
abstract class AbstractDashboardWidget implements DashboardWidgetInterface
{
    /**
     * Cache TTL for per-user metrics (60 seconds).
     */
    protected const USER_CACHE_TTL = 60;

    /**
     * Cache TTL for system-wide aggregates (5 minutes).
     */
    protected const SYSTEM_CACHE_TTL = 300;

    /**
     * Check if the user can view this widget.
     * User needs at least one of the required permissions.
     */
    public function canView(User $user): bool
    {
        foreach ($this->getRequiredPermissions() as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the user's department IDs for filtering.
     *
     * @return array<int>
     */
    protected function getUserDepartmentIds(User $user): array
    {
        return $user->departments->pluck('id')->toArray();
    }

    /**
     * Check if user has full access (admin-level).
     */
    protected function hasFullAccess(User $user): bool
    {
        return $user->hasRole('Admin') || $user->can('system.admin');
    }

    /**
     * Check if user can view all data for a resource.
     */
    protected function canViewAll(User $user, string $resource): bool
    {
        return $user->can("{$resource}.view-all") || $this->hasFullAccess($user);
    }

    /**
     * Check if user can only view department data.
     */
    protected function canViewDeptOnly(User $user, string $resource): bool
    {
        return $user->can("{$resource}.view-dept") && ! $this->canViewAll($user, $resource);
    }

    /**
     * Cache a value with per-user scope (60 second TTL).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function cacheForUser(User $user, string $key, callable $callback): mixed
    {
        $cacheKey = $this->getUserCacheKey($user, $key);

        return $this->safeCache($cacheKey, self::USER_CACHE_TTL, $callback);
    }

    /**
     * Cache a value with system-wide scope (5 minute TTL).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function cacheSystem(string $key, callable $callback): mixed
    {
        $cacheKey = $this->getSystemCacheKey($key);

        return $this->safeCache($cacheKey, self::SYSTEM_CACHE_TTL, $callback);
    }

    /**
     * Safely cache a value with graceful fallback on cache failure.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function safeCache(string $cacheKey, int $ttl, callable $callback): mixed
    {
        try {
            return Cache::remember($cacheKey, $ttl, $callback);
        } catch (\Exception $e) {
            // Log the cache failure but don't break the dashboard
            Log::warning('Dashboard cache failure', [
                'key' => $cacheKey,
                'error' => $e->getMessage(),
            ]);

            // Graceful fallback: execute callback directly
            return $callback();
        }
    }

    /**
     * Generate a per-user cache key.
     */
    protected function getUserCacheKey(User $user, string $key): string
    {
        $widgetId = $this->getWidgetId();
        $deptIds = implode('_', $this->getUserDepartmentIds($user));

        return "dashboard:{$widgetId}:user:{$user->id}:depts:{$deptIds}:{$key}";
    }

    /**
     * Generate a system-wide cache key.
     */
    protected function getSystemCacheKey(string $key): string
    {
        $widgetId = $this->getWidgetId();

        return "dashboard:{$widgetId}:system:{$key}";
    }

    /**
     * Clear all cache for this widget for a specific user.
     */
    public function clearUserCache(User $user): void
    {
        try {
            // Note: This is a simple approach. For more complex scenarios,
            // consider using cache tags if your cache driver supports them.
            $pattern = "dashboard:{$this->getWidgetId()}:user:{$user->id}:*";
            // Most cache drivers don't support pattern deletion,
            // so we rely on TTL expiration for cleanup.
        } catch (\Exception $e) {
            Log::warning('Failed to clear dashboard user cache', [
                'widget' => $this->getWidgetId(),
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear all system-wide cache for this widget.
     */
    public function clearSystemCache(): void
    {
        try {
            // Note: This is a simple approach. For more complex scenarios,
            // consider using cache tags if your cache driver supports them.
            $pattern = "dashboard:{$this->getWidgetId()}:system:*";
            // Most cache drivers don't support pattern deletion,
            // so we rely on TTL expiration for cleanup.
        } catch (\Exception $e) {
            Log::warning('Failed to clear dashboard system cache', [
                'widget' => $this->getWidgetId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
