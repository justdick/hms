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
        // Allow updating draft or rejected claims (for correction before resubmission)
        if (! in_array($insuranceClaim->status, ['draft', 'rejected'])) {
            return false;
        }

        return $this->checkPermission($user, 'insurance.edit-claims');
    }

    public function delete(User $user, InsuranceClaim $insuranceClaim): bool
    {
        // Only allow deleting draft claims
        if ($insuranceClaim->status !== 'draft') {
            return false;
        }

        return $this->checkPermission($user, 'insurance.delete-claims');
    }

    public function vetClaim(User $user, InsuranceClaim $insuranceClaim): bool
    {
        // Only allow vetting pending or draft claims
        if (! in_array($insuranceClaim->status, ['pending_vetting', 'draft'])) {
            return false;
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
        // Check if user has system admin permission first
        if ($user->hasPermissionTo('system.admin')) {
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
