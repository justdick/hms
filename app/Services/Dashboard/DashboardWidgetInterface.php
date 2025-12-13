<?php

namespace App\Services\Dashboard;

use App\Models\User;

/**
 * Contract for dashboard widget services.
 *
 * Each widget service returns metrics and list data for a specific
 * dashboard section. Widgets are shown/hidden based on user permissions.
 */
interface DashboardWidgetInterface
{
    /**
     * Get the unique identifier for this widget.
     */
    public function getWidgetId(): string;

    /**
     * Get the required permissions to view this widget.
     * Returns an array of permission strings - user needs at least one.
     *
     * @return array<string>
     */
    public function getRequiredPermissions(): array;

    /**
     * Check if the user can view this widget.
     */
    public function canView(User $user): bool;

    /**
     * Get metrics data for this widget.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(User $user): array;

    /**
     * Get list data for this widget (e.g., recent items, queues).
     *
     * @return array<string, mixed>
     */
    public function getListData(User $user): array;
}
