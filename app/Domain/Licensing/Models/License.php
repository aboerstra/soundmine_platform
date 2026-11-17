<?php

namespace App\Domain\Licensing\Models;

use App\Domain\Activations\Models\Activation;
use App\Domain\Catalog\Models\Product;
use App\Domain\Customers\Models\Customer;
use App\Domain\Entitlements\Models\Entitlement;
use App\Domain\Licensing\Enums\LicenseStatus;
use App\Domain\OfflineAuthorization\Models\OfflineAuthorizationRequest;
use App\Domain\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class License extends Model
{
    use HasUuids;

    protected $fillable = ['customer_id', 'product_id', 'order_item_id', 'status', 'max_activations', 'starts_at', 'expires_at'];

    protected function casts(): array
    {
        return [
            'status' => LicenseStatus::class,
            'max_activations' => 'integer',
            'starts_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }

    public function activations(): HasMany
    {
        return $this->hasMany(Activation::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(LicenseEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function offlineRequests(): HasMany
    {
        return $this->hasMany(OfflineAuthorizationRequest::class, 'license_id');
    }
}
