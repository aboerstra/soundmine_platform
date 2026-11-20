<?php

namespace Tests\Feature;

use App\Domain\Activations\Models\Activation;
use App\Domain\Entitlements\Models\Entitlement;
use App\Domain\Licensing\Enums\LicenseStatus;
use App\Domain\OfflineAuthorization\Models\LicenseCertificate;
use App\Domain\OfflineAuthorization\Models\OfflineAuthorizationRequest;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesDomainRecords;
use Tests\TestCase;

class DomainFoundationTest extends TestCase
{
    use CreatesDomainRecords, RefreshDatabase;

    public function test_customer_can_own_licenses_for_multiple_products_without_an_order(): void
    {
        $customer = $this->customer();
        $first = $this->license($customer, $this->product());
        $second = $this->license($customer, $this->product('Future Soundmine product'));
        $this->assertCount(2, $customer->licenses);
        $this->assertNotEquals($first->product_id, $second->product_id);
        $this->assertNull($first->order_item_id);
        $this->assertSame(LicenseStatus::Pending, $first->fresh()->status);
    }

    public function test_order_items_preserve_price_and_description_snapshots(): void
    {
        $customer = $this->customer();
        $product = $this->product();
        $order = Order::create(['customer_id' => $customer->id, 'currency' => 'EUR', 'total_minor' => 4900]);
        $item = OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'description' => $product->name, 'quantity' => 1, 'unit_price_minor' => 4900]);
        $license = $this->license($customer, $product, ['order_item_id' => $item->id]);
        $product->update(['name' => 'Renamed']);
        $this->assertSame('TheSoundMine Pattern Conductor', $order->items->first()->description);
        $this->assertSame(4900, $item->unit_price_minor);
        $this->assertTrue($license->orderItem->order->customer->is($customer));
    }

    public function test_entitlements_store_structured_values_and_are_unique_per_license(): void
    {
        $license = $this->license();
        $entitlement = $license->entitlements()->create(['key' => 'formats', 'value' => ['vst3']]);
        $this->assertSame(['vst3'], $entitlement->fresh()->value);
        $this->expectException(QueryException::class);
        $license->entitlements()->create(['key' => 'formats', 'value' => ['vst3']]);
    }

    public function test_same_entitlement_key_can_exist_on_another_license(): void
    {
        foreach ([$this->license(), $this->license()] as $license) {
            $license->entitlements()->create(['key' => 'updates', 'value' => true]);
        }
        $this->assertSame(2, Entitlement::count());
    }

    public function test_installation_cannot_be_registered_twice_for_one_license(): void
    {
        $attributes = ['license_id' => $this->license()->id, 'machine_hash' => str_repeat('a', 64), 'platform' => 'macos'];
        Activation::create($attributes);
        $this->expectException(QueryException::class);
        Activation::create($attributes);
    }

    public function test_offline_request_digest_cannot_be_replayed_as_another_record(): void
    {
        $attributes = ['license_id' => $this->license()->id, 'machine_hash' => str_repeat('a', 64), 'request_hash' => str_repeat('b', 64), 'expires_at' => now()->addHour()];
        OfflineAuthorizationRequest::create($attributes);
        $this->expectException(QueryException::class);
        OfflineAuthorizationRequest::create($attributes);
    }

    public function test_certificate_records_link_to_an_installation_and_offline_request(): void
    {
        $license = $this->license();
        $activation = $license->activations()->create(['machine_hash' => str_repeat('a', 64), 'platform' => 'windows']);
        $request = $license->offlineRequests()->create(['machine_hash' => $activation->machine_hash, 'request_hash' => str_repeat('b', 64), 'expires_at' => now()->addHour()]);
        // Deliberately inert fixture. No certificate signer or key generation exists.
        $certificate = LicenseCertificate::create(['activation_id' => $activation->id, 'offline_authorization_request_id' => $request->id, 'key_id' => 'test-placeholder', 'payload' => ['fixture' => true], 'signature' => 'not-a-real-signature', 'issued_at' => now(), 'expires_at' => now()->addDay()]);
        $this->assertTrue($certificate->activation->license->is($license));
        $this->assertTrue($certificate->offlineAuthorizationRequest->is($request));
        $this->assertSame(['fixture' => true], $certificate->fresh()->payload);
    }

    public function test_database_rejects_negative_order_totals(): void
    {
        $customer = $this->customer();
        $this->expectException(QueryException::class);
        Order::create(['customer_id' => $customer->id, 'currency' => 'EUR', 'total_minor' => -1]);
    }

    public function test_database_rejects_invalid_license_dates(): void
    {
        $customer = $this->customer();
        $product = $this->product();
        $this->expectException(QueryException::class);
        $this->license($customer, $product, ['starts_at' => now(), 'expires_at' => now()->subDay()]);
    }

    public function test_historical_customer_cannot_be_deleted_while_licenses_exist(): void
    {
        $license = $this->license();
        $customer = $license->customer;
        $this->expectException(QueryException::class);
        $customer->delete();
    }

    public function test_product_seed_is_repeatable_and_does_not_create_users_or_licenses(): void
    {
        $this->seed();
        $this->seed();
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseHas('products', [
            'slug' => 'rhythm-conductor',
            'name' => 'TheSoundMine Pattern Conductor',
            'is_active' => false,
        ]);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('licenses', 0);
    }
}
