<?php

namespace App\Domain\Activations\Models;

use App\Domain\Activations\Enums\ActivationStatus;
use App\Domain\Licensing\Models\License;
use App\Domain\OfflineAuthorization\Models\LicenseCertificate;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activation extends Model
{
    use HasUuids;

    protected $fillable = ['license_id', 'machine_hash', 'platform', 'plugin_version', 'status', 'last_seen_at', 'deactivated_at'];

    protected function casts(): array
    {
        return [
            'status' => ActivationStatus::class,
            'last_seen_at' => 'immutable_datetime',
            'deactivated_at' => 'immutable_datetime',
        ];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(LicenseCertificate::class);
    }
}
