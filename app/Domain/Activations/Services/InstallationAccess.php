<?php

namespace App\Domain\Activations\Services;

use App\Domain\Activations\Enums\ActivationStatus;
use App\Domain\Activations\Exceptions\ActivationException;
use App\Domain\Activations\Models\Activation;
use App\Domain\Activations\Models\InstallationToken;
use App\Domain\Catalog\Models\Product;
use App\Domain\Licensing\Enums\LicenseStatus;
use App\Domain\Licensing\Models\License;

class InstallationAccess
{
    public function assertEnabled(): void
    {
        if (! config('soundmine.activations.enabled')) {
            throw new ActivationException('activation_unavailable', 'Online activation is currently unavailable.', 503);
        }
    }

    public function lockLicense(string $id): License
    {
        return License::with(['product', 'customer.user'])->lockForUpdate()->findOrFail($id);
    }

    public function assertLicenseUsable(License $license): void
    {
        $this->assertEnabled();
        if (! $license->customer->user->hasVerifiedEmail()) {
            throw new ActivationException('customer_unverified', 'The customer account must be verified.', 403);
        }
        if (! $license->product->is_active) {
            throw new ActivationException('product_unavailable', 'Online authorization for this product is unavailable.', 403);
        }
        if ($license->status !== LicenseStatus::Active) {
            throw new ActivationException('license_inactive', 'This license is not active.', 403);
        }
        if ($license->starts_at?->isFuture()) {
            throw new ActivationException('license_not_started', 'This license is not valid yet.', 403);
        }
        if ($license->expires_at !== null && $license->expires_at->lessThanOrEqualTo(now())) {
            throw new ActivationException('license_expired', 'This license has expired.', 403);
        }
    }

    public function assertTokenScope(InstallationToken $token, Activation $activation, License $license, Product $product, bool $allowDeactivated = false): void
    {
        if ($token->product_id !== $product->id) {
            throw new ActivationException('resource_not_found', 'The requested resource was not found.', 404);
        }
        if ($license->product_id !== $token->product_id || $license->customer_id !== $token->customer_id
            || $token->expires_at->lessThanOrEqualTo(now())) {
            throw new ActivationException('invalid_installation_token', 'The installation token is invalid or expired.', 401);
        }
        $deactivationRetry = $allowDeactivated && $activation->status === ActivationStatus::Deactivated && $token->revocation_reason === 'deactivated';
        if ($token->revoked_at !== null && ! $deactivationRetry) {
            throw new ActivationException('invalid_installation_token', 'The installation token is invalid or expired.', 401);
        }
    }

    /** @return array{License, Activation, InstallationToken} */
    public function lockInstallation(InstallationToken $credential, Product $product, bool $allowDeactivated = false): array
    {
        $license = $this->lockLicense($credential->activation->license_id);
        $token = InstallationToken::lockForUpdate()->findOrFail($credential->id);
        $activation = Activation::lockForUpdate()->findOrFail($token->activation_id);
        $this->assertTokenScope($token, $activation, $license, $product, $allowDeactivated);

        return [$license, $activation, $token];
    }
}
