<?php

namespace App\Domain\Activations\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallationToken extends Model
{
    use HasUuids;

    protected $fillable = ['activation_id', 'customer_id', 'product_id', 'token_hash', 'expires_at', 'revoked_at', 'revocation_reason'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    public function activation(): BelongsTo
    {
        return $this->belongsTo(Activation::class);
    }
}
