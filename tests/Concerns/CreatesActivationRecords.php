<?php

namespace Tests\Concerns;

use App\Domain\Activations\Actions\IssueActivationAuthorization;
use App\Domain\Catalog\Models\Product;
use App\Domain\Customers\Models\Customer;
use App\Domain\Licensing\Enums\LicenseStatus;
use App\Domain\Licensing\Models\License;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait CreatesActivationRecords
{
    use CreatesDomainRecords;

    protected function activationLicense(?Customer $customer = null, ?Product $product = null, array $attributes = []): License
    {
        $product ??= $this->product();
        $product->update(['is_active' => true]);

        return $this->license($customer, $product, array_merge([
            'status' => LicenseStatus::Active, 'starts_at' => now()->subDay(), 'expires_at' => null,
        ], $attributes));
    }

    protected function authorization(License $license, string $machineHash, string $platform = 'macos'): string
    {
        return app(IssueActivationAuthorization::class)->handle($license->customer->user, $license, ['machine_hash' => $machineHash, 'platform' => $platform])['code'];
    }

    protected function activationPayload(string $code, string $machineHash, string $platform = 'macos'): array
    {
        return ['authorization_code' => $code, 'machine_hash' => $machineHash, 'platform' => $platform, 'plugin_version' => '1.0.0'];
    }

    protected function exchange(License $license, array $payload, ?string $key = null): TestResponse
    {
        return $this->postJson('/api/v1/products/'.$license->product->slug.'/activations', $payload, ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    protected function activate(License $license, string $machineHash, string $platform = 'macos'): TestResponse
    {
        return $this->exchange($license, $this->activationPayload($this->authorization($license, $machineHash, $platform), $machineHash, $platform));
    }

    protected function entitlementsUrl(License $license): string
    {
        return '/api/v1/products/'.$license->product->slug.'/entitlements';
    }

    protected function deactivationUrl(License $license, string $activationId): string
    {
        return '/api/v1/products/'.$license->product->slug.'/activations/'.$activationId;
    }
}
