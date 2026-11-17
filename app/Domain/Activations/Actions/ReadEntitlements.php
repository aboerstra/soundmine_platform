<?php

namespace App\Domain\Activations\Actions;

use App\Domain\Activations\Enums\ActivationStatus;
use App\Domain\Activations\Exceptions\ActivationException;
use App\Domain\Activations\Models\Activation;
use App\Domain\Activations\Models\InstallationToken;
use App\Domain\Activations\Services\InstallationAccess;
use App\Domain\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

class ReadEntitlements
{
    public function __construct(private InstallationAccess $access) {}

    public function handle(InstallationToken $credential, Product $product): Activation
    {
        return DB::transaction(function () use ($credential, $product) {
            [$license, $activation] = $this->access->lockInstallation($credential, $product);
            $this->access->assertLicenseUsable($license);
            if ($activation->status !== ActivationStatus::Active) {
                throw new ActivationException('installation_inactive', 'This installation is not active.', 403);
            }
            $activation->update(['last_seen_at' => now()]);

            return $activation->setRelation('license', $license->load('entitlements'));
        }, attempts: 3);
    }
}
