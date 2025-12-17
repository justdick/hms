<?php

namespace App\Policies;

use App\Models\PatientAccount;
use App\Models\User;

class PatientAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('billing.collect') || $user->can('billing.view-all');
    }

    public function view(User $user, PatientAccount $account): bool
    {
        return $user->can('billing.collect') || $user->can('billing.view-all');
    }

    public function create(User $user): bool
    {
        return $user->can('billing.create');
    }

    public function deposit(User $user): bool
    {
        return $user->can('billing.create');
    }

    public function setCreditLimit(User $user): bool
    {
        return $user->can('billing.manage-credit');
    }

    public function refund(User $user, PatientAccount $account): bool
    {
        return $user->can('billing.refund-deposits');
    }
}
