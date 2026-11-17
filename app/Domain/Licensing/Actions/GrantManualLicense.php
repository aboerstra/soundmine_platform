<?php

namespace App\Domain\Licensing\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Licensing\Enums\LicenseStatus;
use App\Domain\Licensing\Models\License;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GrantManualLicense
{
    public function handle(User $actor, array $data): License
    {
        Gate::forUser($actor)->authorize('create', License::class);
        $data['starts_at'] = $data['starts_at'] ?? now()->toIso8601String();
        $validated = Validator::make($data, [
            'customer_id' => ['required', 'uuid', 'exists:customers,id'],
            'product_id' => ['required', 'uuid', Rule::exists('products', 'id')->where('is_active', true)],
            'max_activations' => ['required', 'integer', 'min:1', 'max:100'],
            'starts_at' => ['required', 'date'], 'expires_at' => ['nullable', 'date', 'after:starts_at'],
            'reason' => ['required', 'string', 'max:1000'],
            'entitlements' => ['sometimes', 'array', 'max:50'],
            'entitlements.*' => ['array:key,value'],
            'entitlements.*.key' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_.-]*$/', 'distinct'],
            'entitlements.*.value' => ['required', 'string', 'max:1000'],
        ])->validate();

        return DB::transaction(function () use ($actor, $validated) {
            $product = Product::lockForUpdate()->findOrFail($validated['product_id']);
            abort_unless($product->is_active, 422, 'This product is not available for license grants.');
            $license = License::create([
                'customer_id' => $validated['customer_id'], 'product_id' => $product->id,
                'status' => LicenseStatus::Active, 'max_activations' => $validated['max_activations'],
                'starts_at' => $validated['starts_at'], 'expires_at' => $validated['expires_at'] ?? null,
            ]);
            foreach ($validated['entitlements'] ?? [] as $entitlement) {
                $license->entitlements()->create(['key' => $entitlement['key'], 'value' => $entitlement['value']]);
            }
            $license->events()->create([
                'actor_id' => $actor->id, 'event' => 'granted', 'from_status' => null,
                'to_status' => LicenseStatus::Active->value, 'reason' => $validated['reason'],
                'snapshot' => ['max_activations' => $license->max_activations, 'starts_at' => $license->starts_at?->toIso8601String(), 'expires_at' => $license->expires_at?->toIso8601String(), 'entitlements' => $validated['entitlements'] ?? []],
            ]);

            return $license;
        });
    }
}
