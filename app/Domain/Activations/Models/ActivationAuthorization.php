<?php

namespace App\Domain\Activations\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class ActivationAuthorization extends Model
{
    use HasUuids, MassPrunable;

    protected $fillable = ['license_id', 'customer_id', 'product_id', 'machine_hash', 'platform', 'token_hash', 'expires_at', 'consumed_at', 'request_id', 'request_fingerprint', 'issued_token_id', 'issued_token'];

    protected $hidden = ['token_hash', 'issued_token', 'request_fingerprint'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'consumed_at' => 'immutable_datetime', 'issued_token' => 'encrypted'];
    }

    public function prunable(): Builder
    {
        return static::where('expires_at', '<=', now());
    }
}
