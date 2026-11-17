<?php

namespace App\Domain\Customers\Policies;

use App\Domain\Customers\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin === true;
    }

    public function create(User $user): bool
    {
        return $user->is_admin === true;
    }

    public function view(User $user, Customer $record): bool
    {
        return $user->is_admin === true;
    }

    public function update(User $user, Customer $record): bool
    {
        return $user->is_admin === true;
    }

    public function delete(User $user, Customer $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
