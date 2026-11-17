<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $license = $this->license;

        return [
            'product' => $license->product->only(['id', 'slug', 'name']),
            'license' => [
                'id' => $license->id, 'status' => $license->status->value,
                'starts_at' => $license->starts_at?->toIso8601String(),
                'expires_at' => $license->expires_at?->toIso8601String(),
                'max_activations' => $license->max_activations,
            ],
            'activation' => ['id' => $this->id, 'status' => $this->status->value, 'platform' => $this->platform],
            'entitlements' => (object) $license->entitlements->pluck('value', 'key')->all(),
        ];
    }
}
