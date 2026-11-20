<?php

namespace Tests\Concerns;

use App\Domain\Catalog\Models\Product;
use App\Domain\Customers\Models\Customer;
use App\Domain\Licensing\Models\License;
use App\Models\User;
use Illuminate\Support\Str;

trait CreatesDomainRecords
{
    protected function administrator(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    protected function customer(?User $user = null): Customer
    {
        return Customer::create(['user_id' => ($user ?? User::factory()->create())->id, 'display_name' => 'Test customer']);
    }

    protected function product(string $name = 'TheSoundMine Pattern Conductor'): Product
    {
        return Product::create(['slug' => Str::slug($name).'-'.Str::random(6), 'name' => $name]);
    }

    protected function license(?Customer $customer = null, ?Product $product = null, array $attributes = []): License
    {
        return License::create(array_merge([
            'customer_id' => ($customer ?? $this->customer())->id,
            'product_id' => ($product ?? $this->product())->id,
        ], $attributes));
    }
}
