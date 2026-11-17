<?php

namespace App\Domain\Activations\Actions;

use App\Domain\Activations\Data\ActivationResult;
use App\Domain\Activations\Enums\ActivationStatus;
use App\Domain\Activations\Exceptions\ActivationException;
use App\Domain\Activations\Models\Activation;
use App\Domain\Activations\Models\ActivationAuthorization;
use App\Domain\Activations\Models\InstallationToken;
use App\Domain\Activations\Services\InstallationAccess;
use App\Domain\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SensitiveParameter;

class ExchangeActivationAuthorization
{
    public function __construct(private InstallationAccess $access) {}

    public function handle(Product $product, #[SensitiveParameter] string $code, string $requestId, array $data): ActivationResult
    {
        $authorization = ActivationAuthorization::where('token_hash', hash('sha256', $code))->first();
        if (! $authorization) {
            throw new ActivationException('invalid_authorization', 'The activation authorization is invalid or expired.', 401);
        }
        $requestId = Str::lower($requestId);
        $fingerprint = hash('sha256', json_encode([$product->id, $data['machine_hash'], $data['platform'], $data['plugin_version'] ?? null], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($product, $authorization, $requestId, $data, $fingerprint) {
            $license = $this->access->lockLicense($authorization->license_id);
            $grant = ActivationAuthorization::lockForUpdate()->find($authorization->id);
            if (! $grant || $grant->expires_at->lessThanOrEqualTo(now()) || $grant->customer_id !== $license->customer_id || $grant->product_id !== $license->product_id) {
                throw new ActivationException('invalid_authorization', 'The activation authorization is invalid or expired.', 401);
            }
            if ($grant->product_id !== $product->id) {
                throw new ActivationException('resource_not_found', 'The requested resource was not found.', 404);
            }
            if (! hash_equals($grant->machine_hash, $data['machine_hash']) || $grant->platform !== $data['platform']) {
                throw new ActivationException('installation_mismatch', 'The authorization does not match this installation.', 403);
            }
            $this->access->assertLicenseUsable($license);
            if ($grant->consumed_at !== null) {
                if ($grant->request_id !== $requestId || $grant->request_fingerprint !== $fingerprint) {
                    throw new ActivationException('authorization_consumed', 'This authorization was already used by another request.', 409);
                }
                $credential = InstallationToken::lockForUpdate()->find($grant->issued_token_id);
                $activation = $credential ? Activation::lockForUpdate()->find($credential->activation_id) : null;
                if (! $credential || $credential->revoked_at !== null || $credential->expires_at->lessThanOrEqualTo(now()) || $activation?->status !== ActivationStatus::Active) {
                    throw new ActivationException('authorization_superseded', 'This authorization can no longer replay an active credential.', 409);
                }

                return new ActivationResult($activation->setRelation('license', $license->load('entitlements')), $grant->issued_token, $credential->expires_at, false);
            }
            $activation = $license->activations()->where('machine_hash', $grant->machine_hash)->lockForUpdate()->first();
            if ($activation && ($activation->status === ActivationStatus::Revoked || $activation->platform !== $grant->platform)) {
                throw new ActivationException('installation_unavailable', 'This installation cannot be activated.', 403);
            }
            $needsSeat = ! $activation || $activation->status !== ActivationStatus::Active;
            if ($needsSeat && $license->activations()->where('status', ActivationStatus::Active)->count() >= $license->max_activations) {
                throw new ActivationException('activation_limit_reached', 'This license has reached its device allowance.', 409);
            }
            $created = $activation === null;
            $activation ??= new Activation(['license_id' => $license->id, 'machine_hash' => $grant->machine_hash, 'platform' => $grant->platform]);
            $activation->fill(['status' => ActivationStatus::Active, 'plugin_version' => $data['plugin_version'] ?? null, 'last_seen_at' => now(), 'deactivated_at' => null])->save();
            InstallationToken::where('activation_id', $activation->id)->whereNull('revoked_at')->update(['revoked_at' => now(), 'revocation_reason' => 'rotated']);
            $plainTextToken = 'sm_install_'.Str::random(64);
            $expiresAt = now()->addDays(config('soundmine.activations.token_days'));
            if ($license->expires_at && $license->expires_at->lessThan($expiresAt)) {
                $expiresAt = $license->expires_at;
            }
            $credential = InstallationToken::create([
                'activation_id' => $activation->id, 'customer_id' => $license->customer_id, 'product_id' => $license->product_id,
                'token_hash' => hash('sha256', $plainTextToken), 'expires_at' => $expiresAt,
            ]);
            $grant->update(['consumed_at' => now(), 'request_id' => $requestId, 'request_fingerprint' => $fingerprint, 'issued_token_id' => $credential->id, 'issued_token' => $plainTextToken]);

            return new ActivationResult($activation->setRelation('license', $license->load('entitlements')), $plainTextToken, $credential->expires_at, $created);
        }, attempts: 3);
    }
}
