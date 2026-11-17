<?php

namespace Tests\Feature;

use App\Domain\Activations\Enums\ActivationStatus;
use App\Domain\Activations\Models\Activation;
use App\Domain\Activations\Models\ActivationAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesActivationRecords;
use Tests\TestCase;

class ActivationApiTest extends TestCase
{
    use CreatesActivationRecords, RefreshDatabase;

    public function test_exchange_and_entitlement_read_return_only_scoped_current_rights(): void
    {
        $license = $this->activationLicense();
        $license->entitlements()->create(['key' => 'formats', 'value' => ['vst3']]);
        $license->entitlements()->create(['key' => 'commercial', 'value' => true]);

        $response = $this->activate($license, str_repeat('a', 64))->assertCreated()
            ->assertJsonPath('data.product.id', $license->product_id)->assertJsonPath('data.license.id', $license->id)
            ->assertJsonPath('data.activation.platform', 'macos')->assertJsonPath('data.entitlements.formats', ['vst3'])
            ->assertJsonPath('data.entitlements.commercial', true)->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonMissingPath('data.customer')->assertJsonMissingPath('data.token_hash');
        $token = $response->json('installation_token');
        $this->assertDatabaseHas('installation_tokens', ['token_hash' => hash('sha256', $token), 'product_id' => $license->product_id]);
        $grant = ActivationAuthorization::sole();
        $this->assertSame($token, $grant->issued_token);
        $this->assertNotSame($token, $grant->getRawOriginal('issued_token'));
        $this->assertArrayNotHasKey('issued_token', $grant->toArray());
        $license->entitlements()->where('key', 'commercial')->firstOrFail()->update(['value' => false]);

        $this->withToken($token)->getJson($this->entitlementsUrl($license))->assertOk()->assertJsonPath('data.entitlements.commercial', false)
            ->assertJsonMissingPath('installation_token')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('activations', 1);
        $this->assertDatabaseCount('license_certificates', 0);
    }

    public function test_same_device_can_activate_multiple_products_without_sharing_tokens_or_rights(): void
    {
        $customer = $this->customer();
        $first = $this->activationLicense($customer);
        $second = $this->activationLicense($customer, $this->product('Future Soundmine product'));
        $first->entitlements()->create(['key' => 'edition', 'value' => 'rhythm-full']);
        $second->entitlements()->create(['key' => 'edition', 'value' => 'future-lite']);
        $hash = str_repeat('a', 64);

        $a = $this->activate($first, $hash)->assertCreated();
        $b = $this->activate($second, $hash)->assertCreated();

        $this->assertNotSame($a->json('installation_token'), $b->json('installation_token'));
        $this->withToken($a->json('installation_token'))->getJson($this->entitlementsUrl($second))->assertNotFound();
        $this->withToken($b->json('installation_token'))->getJson($this->entitlementsUrl($second))->assertOk()->assertJsonPath('data.entitlements.edition', 'future-lite');
        $this->withToken($a->json('installation_token'))->deleteJson($this->deactivationUrl($first, $a->json('data.activation.id')))->assertNoContent();
        $this->withToken($b->json('installation_token'))->getJson($this->entitlementsUrl($second))->assertOk();
        $this->assertSame(1, $second->activations()->where('status', ActivationStatus::Active)->count());
    }

    public function test_an_authorization_cannot_be_exchanged_for_another_product(): void
    {
        $owner = $this->customer();
        $first = $this->activationLicense($owner);
        $other = $this->activationLicense($owner, $this->product('Other product'));
        $code = $this->authorization($first, str_repeat('a', 64));

        $this->exchange($other, $this->activationPayload($code, str_repeat('a', 64)))->assertNotFound();

        $this->assertDatabaseCount('activations', 0);
        $this->assertNull(ActivationAuthorization::sole()->consumed_at);
    }

    public function test_same_product_licenses_have_independent_allowances_and_installations(): void
    {
        $first = $this->activationLicense(attributes: ['max_activations' => 1]);
        $second = $this->activationLicense($first->customer, $first->product, ['max_activations' => 1]);

        $a = $this->activate($first, str_repeat('a', 64))->assertCreated();
        $b = $this->activate($second, str_repeat('a', 64))->assertCreated();

        $this->withToken($a->json('installation_token'))->deleteJson($this->deactivationUrl($first, $b->json('data.activation.id')))->assertNotFound();
        $this->assertDatabaseCount('activations', 2);
    }

    public function test_exact_retry_reuses_allocation_and_token_but_other_requests_cannot_reuse_the_code(): void
    {
        $license = $this->activationLicense();
        $payload = $this->activationPayload($this->authorization($license, str_repeat('a', 64)), str_repeat('a', 64));
        $key = (string) Str::uuid();
        $first = $this->exchange($license, $payload, $key)->assertCreated();

        $this->exchange($license, $payload, $key)->assertOk()->assertJsonPath('installation_token', $first->json('installation_token'))->assertJsonPath('data.activation.id', $first->json('data.activation.id'));
        $this->exchange($license, array_replace($payload, ['plugin_version' => '2.0.0']), $key)->assertConflict()->assertJsonPath('error.code', 'authorization_consumed');
        $this->exchange($license, $payload)->assertConflict();

        $this->assertDatabaseCount('activations', 1);
        $this->assertDatabaseCount('installation_tokens', 1);
    }

    public function test_device_limit_is_enforced_and_deactivation_is_idempotent_and_frees_a_seat(): void
    {
        $license = $this->activationLicense(attributes: ['max_activations' => 1]);
        $first = $this->activate($license, str_repeat('a', 64))->assertCreated();
        $payload = $this->activationPayload($this->authorization($license, str_repeat('b', 64)), str_repeat('b', 64));
        $key = (string) Str::uuid();
        $this->exchange($license, $payload, $key)->assertConflict()->assertJsonPath('error.code', 'activation_limit_reached');
        $token = $first->json('installation_token');
        $url = $this->deactivationUrl($license, $first->json('data.activation.id'));

        $this->withToken($token)->deleteJson($url)->assertNoContent();
        $this->withToken($token)->deleteJson($url)->assertNoContent();
        $this->withToken($token)->getJson($this->entitlementsUrl($license))->assertUnauthorized();
        $this->exchange($license, $payload, $key)->assertCreated();

        $this->assertSame(1, $license->activations()->where('status', ActivationStatus::Active)->count());
    }

    public function test_reauthorization_rotates_credentials_without_consuming_another_seat(): void
    {
        $license = $this->activationLicense(attributes: ['max_activations' => 1]);
        $hash = str_repeat('a', 64);
        $oldPayload = $this->activationPayload($this->authorization($license, $hash), $hash);
        $key = (string) Str::uuid();
        $first = $this->exchange($license, $oldPayload, $key)->assertCreated();

        $next = $this->activate($license, $hash)->assertOk();

        $this->assertSame($first->json('data.activation.id'), $next->json('data.activation.id'));
        $this->assertNotSame($first->json('installation_token'), $next->json('installation_token'));
        $this->withToken($first->json('installation_token'))->getJson($this->entitlementsUrl($license))->assertUnauthorized();
        $this->exchange($license, $oldPayload, $key)->assertConflict()->assertJsonPath('error.code', 'authorization_superseded');
        $this->assertDatabaseCount('activations', 1);
    }

    public function test_reactivation_reuses_the_installation_but_an_old_token_cannot_deactivate_it(): void
    {
        $license = $this->activationLicense();
        $first = $this->activate($license, str_repeat('a', 64))->assertCreated();
        $url = $this->deactivationUrl($license, $first->json('data.activation.id'));
        $this->withToken($first->json('installation_token'))->deleteJson($url)->assertNoContent();

        $next = $this->activate($license, str_repeat('a', 64))->assertOk();

        $this->assertSame($first->json('data.activation.id'), $next->json('data.activation.id'));
        $this->withToken($first->json('installation_token'))->deleteJson($url)->assertUnauthorized();
        $this->withToken($next->json('installation_token'))->getJson($this->entitlementsUrl($license))->assertOk();
    }

    public static function inactiveLicenseStates(): array
    {
        return ['pending' => ['pending'], 'suspended' => ['suspended'], 'revoked' => ['revoked']];
    }

    #[DataProvider('inactiveLicenseStates')]
    public function test_inactive_license_cannot_activate_or_read_but_can_release_an_installation(string $status): void
    {
        $license = $this->activationLicense();
        $first = $this->activate($license, str_repeat('a', 64))->assertCreated();
        $code = $this->authorization($license, str_repeat('b', 64));
        $license->update(['status' => $status]);

        $this->withToken($first->json('installation_token'))->getJson($this->entitlementsUrl($license))->assertForbidden()->assertJsonPath('error.code', 'license_inactive');
        $this->exchange($license, $this->activationPayload($code, str_repeat('b', 64)))->assertForbidden();
        $this->withToken($first->json('installation_token'))->deleteJson($this->deactivationUrl($license, $first->json('data.activation.id')))->assertNoContent();
    }

    public function test_current_dates_customer_verification_and_product_availability_are_enforced(): void
    {
        $license = $this->activationLicense();
        $first = $this->activate($license, str_repeat('a', 64))->assertCreated();
        $token = $first->json('installation_token');
        $license->update(['starts_at' => now()->addDay()]);
        $this->withToken($token)->getJson($this->entitlementsUrl($license))->assertForbidden()->assertJsonPath('error.code', 'license_not_started');
        $license->update(['starts_at' => now()->subDays(2), 'expires_at' => now()->subDay()]);
        $this->withToken($token)->getJson($this->entitlementsUrl($license))->assertForbidden()->assertJsonPath('error.code', 'license_expired');
        $license->update(['expires_at' => null]);
        $license->product->update(['is_active' => false]);
        $this->withToken($token)->getJson($this->entitlementsUrl($license))->assertForbidden()->assertJsonPath('error.code', 'product_unavailable');
        $license->product->update(['is_active' => true]);
        $license->customer->user->forceFill(['email_verified_at' => null])->save();
        $this->withToken($token)->getJson($this->entitlementsUrl($license))->assertForbidden()->assertJsonPath('error.code', 'customer_unverified');
    }

    public function test_expired_code_cannot_replay_a_secret_but_its_installation_token_remains_usable(): void
    {
        $this->freezeTime();
        $license = $this->activationLicense();
        $payload = $this->activationPayload($this->authorization($license, str_repeat('a', 64)), str_repeat('a', 64));
        $key = (string) Str::uuid();
        $first = $this->exchange($license, $payload, $key)->assertCreated();
        $this->travel(10)->minutes();

        $this->exchange($license, $payload, $key)->assertUnauthorized()->assertJsonMissingPath('installation_token');
        $this->withToken($first->json('installation_token'))->getJson($this->entitlementsUrl($license))->assertOk();
        $this->artisan('model:prune', ['--model' => [ActivationAuthorization::class]])->assertSuccessful();

        $this->assertDatabaseCount('activation_authorizations', 0);
        $this->assertDatabaseCount('installation_tokens', 1);
    }

    public function test_expired_installation_token_requires_customer_reauthorization(): void
    {
        $this->freezeTime();
        $license = $this->activationLicense(attributes: ['max_activations' => 1]);
        $first = $this->activate($license, str_repeat('a', 64))->assertCreated();
        $this->travel(30)->days();

        $this->withToken($first->json('installation_token'))->getJson($this->entitlementsUrl($license))->assertUnauthorized();
        $this->activate($license, str_repeat('a', 64))->assertOk();

        $this->assertDatabaseCount('activations', 1);
    }

    public function test_token_expiry_never_exceeds_the_license_end(): void
    {
        $this->freezeTime();
        $license = $this->activationLicense(attributes: ['expires_at' => now()->addHour()]);

        $this->activate($license, str_repeat('a', 64))->assertCreated()->assertJsonPath('token_expires_at', $license->expires_at->toIso8601String());
    }

    public function test_bearer_scope_does_not_follow_a_transferred_license(): void
    {
        $license = $this->activationLicense();
        $first = $this->activate($license, str_repeat('a', 64))->assertCreated();
        $code = $this->authorization($license, str_repeat('b', 64));
        $license->update(['customer_id' => $this->customer()->id]);

        $this->withToken($first->json('installation_token'))->getJson($this->entitlementsUrl($license))->assertUnauthorized();
        $this->exchange($license, $this->activationPayload($code, str_repeat('b', 64)))->assertUnauthorized();
    }

    public function test_code_is_bound_to_machine_and_platform_and_failure_does_not_consume_it(): void
    {
        $license = $this->activationLicense();
        $code = $this->authorization($license, str_repeat('a', 64));

        $this->exchange($license, $this->activationPayload($code, str_repeat('b', 64)))->assertForbidden()->assertJsonPath('error.code', 'installation_mismatch');
        $this->exchange($license, $this->activationPayload($code, str_repeat('a', 64), 'windows'))->assertForbidden();

        $this->assertNull(ActivationAuthorization::sole()->consumed_at);
        $this->assertDatabaseCount('activations', 0);
    }

    public function test_revoked_installation_and_platform_changes_cannot_be_reactivated(): void
    {
        $license = $this->activationLicense();
        $first = $this->activate($license, str_repeat('a', 64))->assertCreated();
        $this->activate($license, str_repeat('a', 64), 'windows')->assertForbidden();
        Activation::sole()->update(['status' => ActivationStatus::Revoked]);

        $this->activate($license, str_repeat('a', 64))->assertForbidden()->assertJsonPath('error.code', 'installation_unavailable');
        $this->withToken($first->json('installation_token'))->getJson($this->entitlementsUrl($license))->assertForbidden();

        $this->assertDatabaseCount('installation_tokens', 1);
    }

    public function test_session_authentication_and_query_tokens_do_not_authorize_installations(): void
    {
        $license = $this->activationLicense();

        $this->actingAs($license->customer->user)->getJson($this->entitlementsUrl($license))->assertUnauthorized();
        $this->getJson($this->entitlementsUrl($license).'?token=pretend-token')->assertUnauthorized();
    }

    public function test_requests_require_json_and_an_idempotency_header_and_cannot_select_a_license(): void
    {
        $license = $this->activationLicense();
        $other = $this->activationLicense();
        $payload = $this->activationPayload($this->authorization($license, str_repeat('a', 64)), str_repeat('a', 64));
        $url = '/api/v1/products/'.$license->product->slug.'/activations';
        $this->postJson($url, $payload + ['request_id' => (string) Str::uuid()])->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');
        $this->post($url, $payload)->assertStatus(415);

        $response = $this->exchange($license, $payload + ['license_id' => $other->id, 'customer_id' => $other->customer_id, 'status' => 'revoked'])->assertCreated();

        $this->assertSame($license->id, $response->json('data.license.id'));
        $this->assertDatabaseHas('activations', ['license_id' => $license->id, 'status' => 'active']);
    }

    public function test_invalid_payload_does_not_create_any_installation(): void
    {
        $license = $this->activationLicense();

        $this->exchange($license, ['authorization_code' => 'not-a-code', 'machine_hash' => 'invalid', 'platform' => 'linux'])->assertUnprocessable()->assertJsonValidationErrors(['authorization_code', 'machine_hash', 'platform']);

        $this->assertDatabaseCount('activations', 0);
        $this->assertDatabaseCount('installation_tokens', 0);
    }

    public function test_feature_can_be_disabled_and_exchange_requests_are_rate_limited(): void
    {
        $license = $this->activationLicense();
        $payload = $this->activationPayload('sm_auth_'.str_repeat('x', 64), str_repeat('a', 64));
        config(['soundmine.activations.enabled' => false]);
        $this->exchange($license, $payload)->assertStatus(503);
        $this->getJson('/api/v1/status')->assertOk()->assertJsonPath('online_activation_available', false);
        config(['soundmine.activations.enabled' => true]);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2']);
        for ($i = 0; $i < 30; $i++) {
            $this->exchange($license, $payload)->assertUnauthorized();
        }

        $this->exchange($license, $payload)->assertTooManyRequests()->assertJsonPath('error.code', 'rate_limited')->assertHeader('Retry-After');

        $this->assertDatabaseCount('activations', 0);
    }

    public function test_display_name_changes_do_not_break_product_identity_or_client_tokens(): void
    {
        $license = $this->activationLicense();
        $response = $this->activate($license, str_repeat('a', 64))->assertCreated();
        $license->product->update(['name' => 'Updated display name']);

        $this->withToken($response->json('installation_token'))->getJson($this->entitlementsUrl($license))->assertOk()->assertJsonPath('data.product.name', 'Updated display name')->assertJsonPath('data.product.id', $license->product_id);
    }
}
