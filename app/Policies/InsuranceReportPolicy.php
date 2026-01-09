<?php

namespace App\Policies;

use App\Models\User;

class InsuranceReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'insurance.view-reports');
    }

    public function exportReports(User $user): bool
    {
        return $this->checkPermission($user, 'insurance.export-reports');
    }

    public function viewClaimsSummary(User $user): bool
    {
        return $this->checkPermission($user, 'insurance.view-reports');
    }

    public function viewRevenueAnalysis(User $user): bool
    {
        return $this->checkPermission($user, 'insurance.view-reports');
    }

    public function viewOutstandingClaims(User $user): bool
    {
        return $this->checkPermission($user, 'insurance.view-reports');
    }

    public function viewVettingPerformance(User $user): bool
    {
        return $this->checkPermission($user, 'insurance.view-reports');
    }

    public function viewUtilizationReport(User $user): bool
    {
        return $this->checkPermission($user, 'insurance.view-reports');
    }

    public function viewRejectionAnalysis(User $user): bool
    {
        return $this->checkPermission($user, 'insurance.view-reports');
    }

    private function checkPermission(User $user, string $permission): bool
    {
        // Check if user has Admin role first (more reliable than checking system.admin permission)
        if ($user->hasRole('Admin')) {
            return true;
        }

        // Check if the specific permission exists before checking
        try {
            return $user->hasPermissionTo($permission);
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            return false;
        }
    }
}
