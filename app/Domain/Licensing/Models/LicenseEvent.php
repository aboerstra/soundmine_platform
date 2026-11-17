<?php

namespace App\Domain\Licensing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class LicenseEvent extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['license_id', 'actor_id', 'event', 'from_status', 'to_status', 'reason', 'snapshot'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('License history cannot be changed.'));
        static::deleting(fn () => throw new LogicException('License history cannot be deleted.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
