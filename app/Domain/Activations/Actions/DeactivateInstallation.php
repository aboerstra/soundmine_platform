<?php

namespace App\Domain\Activations\Actions;

use App\Domain\Activations\Enums\ActivationStatus;
use App\Domain\Activations\Exceptions\ActivationException;
use App\Domain\Activations\Models\InstallationToken;
use App\Domain\Activations\Services\InstallationAccess;
use App\Domain\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

class DeactivateInstallation
{
    public function __construct(private InstallationAccess $access) {}

    public function handle(InstallationToken $credential, Product $product, string $activationId): void
    {
        $this->access->assertEnabled();
        DB::transaction(function () use ($credential, $product, $activationId) {
            [, $activation] = $this->access->lockInstallation($credential, $product, allowDeactivated: true);
            if ($activation->id !== $activationId) {
                throw new ActivationException('resource_not_found', 'The requested resource was not found.', 404);
            }
            if ($activation->status === ActivationStatus::Deactivated) {
                return;
            }
            if ($activation->status !== ActivationStatus::Active) {
                throw new ActivationException('installation_inactive', 'This installation is not active.', 403);
            }
            $activation->update(['status' => ActivationStatus::Deactivated, 'deactivated_at' => now()]);
            InstallationToken::where('activation_id', $activation->id)->whereNull('revoked_at')->update(['revoked_at' => now(), 'revocation_reason' => 'deactivated']);
        }, attempts: 3);
    }
}
