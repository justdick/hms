<?php

namespace App\Policies;

use App\Models\InsuranceClaim;
use App\Models\User;

class InsuranceClaimPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'insurance.view-claims');
    }

    public function view(User $user, InsuranceClaim $insuranceClaim): bool
    {
        return $this->checkPermission($user, 'insurance.view-claims');
    }

    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'insurance.create-claims');
    }

    public function update(User $user, InsuranceClaim $insuranceClaim): bool
    {
        // Admins can update claims in any editable status
        if ($user->hasRole('Admin')) {
            // Only block truly locked statuses (submitted, approved, paid)
            if (in_array($insuranceClaim->status, ['submitted', 'approved', 'paid'])) {
                return false;
            }

            return true;
        }

        // Allow updating draft, rejected, pending_vetting, or vetted claims (before submission)
        if (! in_array($insuranceClaim->status, ['draft', 'rejected', 'pending_vetting', 'vetted'])) {
            return false;
        }

        // For vetted claims, block if already in a finalized/submitted batch
        if ($insuranceClaim->status === 'vetted') {
            $inNonDraftBatch = $insuranceClaim->batchItems()
                ->whereHas('batch', function ($query) {
                    $query->whereIn('status', ['finalized', 'submitted', 'completed']);
                })
                ->exists();

            if ($inNonDraftBatch) {
                return false;
            }
        }

        return $this->checkPermission($user, 'insurance.edit-claims');
    }

    public function delete(User $user, InsuranceClaim $insuranceClaim): bool
    {
        // Only allow deleting draft or pending_vetting claims
        if (! in_array($insuranceClaim->status, ['draft', 'pending_vetting'])) {
            return false;
        }

        return $this->checkPermission($user, 'insurance.delete-claims');
    }

    public function vetClaim(User $user, InsuranceClaim $insuranceClaim): bool
    {
        // Allow vetting pending/draft claims OR editing vetted claims before submission
        if (! in_array($insuranceClaim->status, ['pending_vetting', 'draft', 'vetted'])) {
            return false;
        }

        // For vetted claims, check if they're in a non-draft batch (finalized/submitted/completed)
        // Admins can still edit vetted claims even if they're in a non-draft batch
        if ($insuranceClaim->status === 'vetted' && ! $user->hasRole('Admin')) {
            $inNonDraftBatch = $insuranceClaim->batchItems()
                ->whereHas('batch', function ($query) {
                    $query->whereIn('status', ['finalized', 'submitted', 'completed']);
                })
                ->exists();

            if ($inNonDraftBatch) {
                return false;
            }
        }

        return $this->checkPermission($user, 'insurance.vet-claims');
    }

    public function submitClaim(User $user, InsuranceClaim $insuranceClaim): bool
    {
        // Only allow submitting vetted claims
        if ($insuranceClaim->status !== 'vetted') {
            return false;
        }

        return $this->checkPermission($user, 'insurance.submit-claims');
    }

    public function approveClaim(User $user, InsuranceClaim $insuranceClaim): bool
    {
        // Only allow approving submitted claims
        if ($insuranceClaim->status !== 'submitted') {
            return false;
        }

        return $this->checkPermission($user, 'insurance.approve-claims');
    }

    public function rejectClaim(User $user, InsuranceClaim $insuranceClaim): bool
    {
        if (! in_array($insuranceClaim->status, ['submitted', 'pending_vetting', 'vetted'])) {
            return false;
        }

        return $this->checkPermission($user, 'insurance.reject-claims');
    }

    public function markAsPaid(User $user, InsuranceClaim $insuranceClaim): bool
    {
        // Only allow marking approved or submitted claims as paid
        if (! in_array($insuranceClaim->status, ['approved', 'submitted'])) {
            return false;
        }

        return $this->checkPermission($user, 'insurance.mark-claims-paid');
    }

    public function recordPayment(User $user, InsuranceClaim $insuranceClaim): bool
    {
        // Only allow recording payment for approved or submitted claims
        if (! in_array($insuranceClaim->status, ['approved', 'submitted'])) {
            return false;
        }

        return $this->checkPermission($user, 'insurance.record-payments');
    }

    public function handleRejection(User $user, InsuranceClaim $insuranceClaim): bool
    {
        // Only allow handling rejected claims
        if ($insuranceClaim->status !== 'rejected') {
            return false;
        }

        return $this->checkPermission($user, 'insurance.handle-rejections');
    }

    public function resubmitClaim(User $user, InsuranceClaim $insuranceClaim): bool
    {
        // Only allow resubmitting rejected or draft claims
        if (! in_array($insuranceClaim->status, ['rejected', 'draft'])) {
            return false;
        }

        return $this->checkPermission($user, 'insurance.resubmit-claims');
    }

    public function exportClaims(User $user): bool
    {
        return $this->checkPermission($user, 'insurance.export-claims');
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
