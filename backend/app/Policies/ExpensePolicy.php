<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use App\Support\Permissions;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::EXPENSE_VIEW);
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->can(Permissions::EXPENSE_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permissions::EXPENSE_CREATE);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->can(Permissions::EXPENSE_UPDATE);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->can(Permissions::EXPENSE_DELETE);
    }
}
