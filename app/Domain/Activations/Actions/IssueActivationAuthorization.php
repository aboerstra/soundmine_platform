<?php

namespace App\Domain\Activations\Actions;

use App\Domain\Activations\Models\ActivationAuthorization;
use App\Domain\Activations\Services\InstallationAccess;
use App\Domain\Licensing\Models\License;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class IssueActivationAuthorization
{
    public function __construct(private InstallationAccess $access) {}

    /** @return array{code: string, expires_at: string} */
    public function handle(User $user, License $license, array $data): array
    {
        Gate::forUser($user)->authorize('authorizeInstallation', $license);
        $validated = Validator::make($data, [
            'machine_hash' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'platform' => ['required', 'in:macos,windows'],
        ])->validate();

        return DB::transaction(function () use ($user, $license, $validated) {
            $locked = $this->access->lockLicense($license->id);
            Gate::forUser($user)->authorize('authorizeInstallation', $locked);
            $this->access->assertLicenseUsable($locked);
            $code = 'sm_auth_'.Str::random(64);
            $authorization = ActivationAuthorization::create([
                'license_id' => $locked->id, 'customer_id' => $locked->customer_id, 'product_id' => $locked->product_id,
                'machine_hash' => $validated['machine_hash'], 'platform' => $validated['platform'],
                'token_hash' => hash('sha256', $code), 'expires_at' => now()->addMinutes(config('soundmine.activations.authorization_minutes')),
            ]);

            return ['code' => $code, 'expires_at' => $authorization->expires_at->toIso8601String()];
        }, attempts: 3);
    }
}
