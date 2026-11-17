<?php

namespace App\Domain\Customers\Policies;

use App\Domain\Customers\Models\CustomerInvitation;
use App\Models\User;

class CustomerInvitationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin === true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function view(User $user, CustomerInvitation $record): bool
    {
        return $user->is_admin === true;
    }

    public function update(User $user, CustomerInvitation $record): bool
    {
        return false;
    }

    public function delete(User $user, CustomerInvitation $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function resend(User $user, CustomerInvitation $record): bool
    {
        return $user->is_admin === true && $record->accepted_at === null;
    }
}
