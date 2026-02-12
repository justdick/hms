<?php

namespace App\Policies;

use App\Models\ClaimBatch;
use App\Models\User;

class ClaimBatchPolicy
{
    /**
     * Determine whether the user can view any claim batches.
     */
    public function viewAny(User $user): bool
    {
        return $this->checkPermission($user, 'insurance.view-batches');
    }

    /**
     * Determine whether the user can view the claim batch.
     */
    public function view(User $user, ClaimBatch $claimBatch): bool
    {
        return $this->checkPermission($user, 'insurance.view-batches');
    }

    /**
     * Determine whether the user can create claim batches.
     */
    public function create(User $user): bool
    {
        return $this->checkPermission($user, 'insurance.manage-batches');
    }

    /**
     * Determine whether the user can update the claim batch.
     * Only draft batches can be updated.
     */
    public function update(User $user, ClaimBatch $claimBatch): bool
    {
        if (! $claimBatch->canBeModified()) {
            return false;
        }

        return $this->checkPermission($user, 'insurance.manage-batches');
    }

    /**
     * Determine whether the user can delete the claim batch.
     * Only draft batches can be deleted.
     */
    public function delete(User $user, ClaimBatch $claimBatch): bool
    {
        if (! $claimBatch->isDraft()) {
            return false;
        }

        return $this->checkPermission($user, 'insurance.manage-batches');
    }

    /**
     * Determine whether the user can finalize the claim batch.
     * Only draft batches can be finalized.
     */
    public function finalize(User $user, ClaimBatch $claimBatch): bool
    {
        if (! $claimBatch->isDraft()) {
            return false;
        }

        return $this->checkPermission($user, 'insurance.manage-batches');
    }

    /**
     * Determine whether the user can submit the claim batch.
     * Only finalized batches can be submitted.
     */
    public function submit(User $user, ClaimBatch $claimBatch): bool
    {
        if (! $claimBatch->isFinalized()) {
            return false;
        }

        return $this->checkPermission($user, 'insurance.submit-batches');
    }

    /**
     * Determine whether the user can revert the batch to draft.
     * Finalized batches can be reverted by batch managers.
     * Submitted batches can only be reverted by admins.
     */
    public function revertToDraft(User $user, ClaimBatch $claimBatch): bool
    {
        if ($claimBatch->isSubmitted()) {
            return $user->hasRole('Admin');
        }

        if ($claimBatch->isFinalized()) {
            return $this->checkPermission($user, 'insurance.manage-batches');
        }

        return false;
    }

    /**
     * Determine whether the user can export the claim batch.
     */
    public function export(User $user, ClaimBatch $claimBatch): bool
    {
        return $this->checkPermission($user, 'insurance.export-batches');
    }

    /**
     * Determine whether the user can record NHIA response for the batch.
     * Only submitted batches can have responses recorded.
     */
    public function recordResponse(User $user, ClaimBatch $claimBatch): bool
    {
        if (! $claimBatch->isSubmitted()) {
            return false;
        }

        return $this->checkPermission($user, 'insurance.record-batch-responses');
    }

    /**
     * Check if user has the given permission.
     */
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
