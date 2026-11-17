<?php

namespace App\Domain\OfflineAuthorization\Models;

use App\Domain\Licensing\Models\License;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineAuthorizationRequest extends Model
{
    use HasUuids;

    protected $fillable = ['license_id', 'machine_hash', 'request_hash', 'expires_at', 'consumed_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'consumed_at' => 'immutable_datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}
