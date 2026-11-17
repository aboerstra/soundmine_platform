<?php

namespace App\Domain\Customers\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CustomerInvitation extends Model
{
    use HasUuids;

    protected $fillable = ['email', 'name', 'token_hash', 'invited_by', 'expires_at', 'accepted_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime'];
    }

    public function accepts(string $token): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture()
            && hash_equals($this->token_hash, hash('sha256', $token));
    }
}
