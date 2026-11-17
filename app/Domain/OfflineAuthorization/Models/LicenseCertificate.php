<?php

namespace App\Domain\OfflineAuthorization\Models;

use App\Domain\Activations\Models\Activation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseCertificate extends Model
{
    use HasUuids;

    protected $fillable = ['activation_id', 'offline_authorization_request_id', 'key_id', 'schema_version', 'payload', 'signature', 'issued_at', 'expires_at', 'revoked_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'schema_version' => 'integer',
            'issued_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    public function activation(): BelongsTo
    {
        return $this->belongsTo(Activation::class);
    }

    public function offlineAuthorizationRequest(): BelongsTo
    {
        return $this->belongsTo(OfflineAuthorizationRequest::class);
    }
}
