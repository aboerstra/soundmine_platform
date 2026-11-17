<?php

namespace App\Domain\Catalog\Policies;

use App\Domain\Catalog\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin === true;
    }

    public function create(User $user): bool
    {
        return $user->is_admin === true;
    }

    public function view(User $user, Product $record): bool
    {
        return $user->is_admin === true;
    }

    public function update(User $user, Product $record): bool
    {
        return $user->is_admin === true;
    }

    public function delete(User $user, Product $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
