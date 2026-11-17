<?php

namespace App\Domain\Licensing\Policies;

use App\Domain\Licensing\Models\License;
use App\Models\User;

class LicensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin === true;
    }

    public function create(User $user): bool
    {
        return $user->is_admin === true;
    }

    public function view(User $user, License $record): bool
    {
        return $user->is_admin === true || $record->customer()->where('user_id', $user->id)->exists();
    }

    public function authorizeInstallation(User $user, License $record): bool
    {
        return $user->hasVerifiedEmail() && $record->customer()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, License $record): bool
    {
        return false;
    }

    public function delete(User $user, License $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function suspend(User $user, License $record): bool
    {
        return $user->is_admin === true;
    }

    public function resume(User $user, License $record): bool
    {
        return $user->is_admin === true;
    }

    public function revoke(User $user, License $record): bool
    {
        return $user->is_admin === true;
    }
}
