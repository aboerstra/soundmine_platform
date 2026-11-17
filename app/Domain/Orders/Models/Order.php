<?php

namespace App\Domain\Orders\Models;

use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasUuids;

    protected $fillable = ['customer_id', 'status', 'currency', 'total_minor'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_minor' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
