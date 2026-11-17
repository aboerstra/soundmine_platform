<?php

namespace App\Domain\Licensing\Actions;

use App\Domain\Licensing\Enums\LicenseStatus;
use App\Domain\Licensing\Models\License;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ChangeLicenseStatus
{
    public function handle(User $actor, License $license, LicenseStatus $target, string $reason): License
    {
        $ability = match ($target) {
            LicenseStatus::Suspended => 'suspend', LicenseStatus::Active => 'resume',
            LicenseStatus::Revoked => 'revoke', default => throw ValidationException::withMessages(['status' => 'This license transition is not supported.']),
        };
        Gate::forUser($actor)->authorize($ability, $license);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'max:1000']])->validate();

        return DB::transaction(function () use ($actor, $license, $target, $reason, $ability) {
            $locked = License::lockForUpdate()->findOrFail($license->id);
            $allowed = match ($target) {
                LicenseStatus::Suspended => [LicenseStatus::Active],
                LicenseStatus::Active => [LicenseStatus::Suspended],
                LicenseStatus::Revoked => [LicenseStatus::Pending, LicenseStatus::Active, LicenseStatus::Suspended],
                default => [],
            };
            if (! in_array($locked->status, $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'This license transition is not allowed.']);
            }
            $previous = $locked->status;
            $locked->update(['status' => $target]);
            $locked->events()->create([
                'actor_id' => $actor->id, 'event' => $ability, 'from_status' => $previous->value,
                'to_status' => $target->value, 'reason' => $reason, 'snapshot' => ['status' => $target->value],
            ]);

            return $locked;
        });
    }
}
