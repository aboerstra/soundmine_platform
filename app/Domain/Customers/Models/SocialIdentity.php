<?php

namespace App\Domain\Customers\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialIdentity extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'provider', 'provider_subject'];

    protected $hidden = ['provider_subject'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
