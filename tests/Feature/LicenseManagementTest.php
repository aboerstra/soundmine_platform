<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Customers\Models\Customer;
use App\Domain\Licensing\Actions\ChangeLicenseStatus;
use App\Domain\Licensing\Actions\GrantManualLicense;
use App\Domain\Licensing\Enums\LicenseStatus;
use App\Domain\Licensing\Models\License;
use App\Livewire\Portal\LicenseDetails;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesDomainRecords;
use Tests\TestCase;

class LicenseManagementTest extends TestCase
{
    use CreatesDomainRecords, RefreshDatabase;

    private function grantData(): array
    {
        $product = $this->product();
        $product->update(['is_active' => true]);

        return ['customer_id' => $this->customer()->id, 'product_id' => $product->id, 'max_activations' => 2, 'reason' => 'Manual customer grant', 'entitlements' => [['key' => 'edition', 'value' => 'full']]];
    }

    public function test_manual_grant_creates_rights_and_audit_atomically_without_purchase_or_activation(): void
    {
        $admin = $this->administrator();
        $data = $this->grantData();

        $license = app(GrantManualLicense::class)->handle($admin, $data + ['status' => 'revoked', 'order_item_id' => 'injected']);

        $this->assertSame(LicenseStatus::Active, $license->status);
        $this->assertNull($license->order_item_id);
        $this->assertSame('full', $license->entitlements->sole()->value);
        $event = $license->events->sole();
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertSame('Manual customer grant', $event->reason);
        $this->assertSame('granted', $event->event);
        $this->assertSame('active', $event->to_status);
        $this->assertSame([['key' => 'edition', 'value' => 'full']], $event->snapshot['entitlements']);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('activations', 0);
        $this->assertDatabaseCount('license_certificates', 0);
    }

    public function test_customer_cannot_grant_or_change_license_state(): void
    {
        $customer = $this->customer();
        $data = $this->grantData();
        $license = $this->license($customer, attributes: ['status' => LicenseStatus::Active]);

        $this->assertThrows(fn () => app(GrantManualLicense::class)->handle($customer->user, $data), AuthorizationException::class);
        $this->assertThrows(fn () => app(ChangeLicenseStatus::class)->handle($customer->user, $license, LicenseStatus::Revoked, 'Attempted change'), AuthorizationException::class);

        $this->assertSame(LicenseStatus::Active, $license->fresh()->status);
        $this->assertDatabaseCount('license_events', 0);
        $this->assertDatabaseCount('licenses', 1);
    }

    public function test_suspend_resume_and_revoke_preserve_ordered_history_and_revocation_is_terminal(): void
    {
        $admin = $this->administrator();
        $license = app(GrantManualLicense::class)->handle($admin, $this->grantData());
        $change = app(ChangeLicenseStatus::class);

        $change->handle($admin, $license, LicenseStatus::Suspended, 'Support investigation');
        $change->handle($admin, $license, LicenseStatus::Active, 'Investigation resolved');
        $change->handle($admin, $license, LicenseStatus::Revoked, 'Grant cancelled');

        $this->assertSame(LicenseStatus::Revoked, $license->fresh()->status);
        $this->assertSame(['granted', 'suspend', 'resume', 'revoke'], $license->events()->pluck('event')->all());
        $this->assertThrows(fn () => $change->handle($admin, $license, LicenseStatus::Active, 'Try to restore'), ValidationException::class);
        $this->assertDatabaseCount('license_events', 4);
    }

    public function test_repeated_or_invalid_transition_does_not_append_history(): void
    {
        $admin = $this->administrator();
        $license = $this->license(attributes: ['status' => LicenseStatus::Active]);

        $this->assertThrows(fn () => app(ChangeLicenseStatus::class)->handle($admin, $license, LicenseStatus::Active, 'Already active'), ValidationException::class);

        $this->assertSame(LicenseStatus::Active, $license->fresh()->status);
        $this->assertDatabaseCount('license_events', 0);
    }

    public static function invalidGrants(): array
    {
        return [
            'zero device allowance' => [['max_activations' => 0]],
            'excessive device allowance' => [['max_activations' => 101]],
            'missing reason' => [['reason' => '']],
            'invalid interval' => [['starts_at' => '2026-11-17', 'expires_at' => '2026-11-16']],
            'duplicate entitlement keys' => [['entitlements' => [['key' => 'edition', 'value' => 'full'], ['key' => 'edition', 'value' => 'lite']]]],
            'invalid entitlement key' => [['entitlements' => [['key' => 'Bad key', 'value' => 'full']]]],
        ];
    }

    #[DataProvider('invalidGrants')]
    public function test_invalid_manual_grant_writes_nothing(array $invalid): void
    {
        $admin = $this->administrator();
        $data = array_replace($this->grantData(), $invalid);

        $this->assertThrows(fn () => app(GrantManualLicense::class)->handle($admin, $data), ValidationException::class);

        $this->assertDatabaseCount('licenses', 0);
        $this->assertDatabaseCount('entitlements', 0);
        $this->assertDatabaseCount('license_events', 0);
    }

    public function test_inactive_products_cannot_receive_manual_grants(): void
    {
        $admin = $this->administrator();
        $data = $this->grantData();
        Product::findOrFail($data['product_id'])->update(['is_active' => false]);

        $this->assertThrows(fn () => app(GrantManualLicense::class)->handle($admin, $data), ValidationException::class);

        $this->assertDatabaseCount('licenses', 0);
    }

    public function test_customer_details_hide_other_customers_records_and_audit_notes(): void
    {
        $owner = $this->customer();
        $license = $this->license($owner);
        $license->entitlements()->create(['key' => 'edition', 'value' => '<script>alert(1)</script>']);
        $other = $this->license();

        $this->actingAs($owner->user)->get(route('portal.licenses.show', $license))->assertOk()->assertSee('edition')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('portal.licenses.show', $other))->assertNotFound();
        Livewire::actingAs($owner->user)->test(LicenseDetails::class, ['license' => $other->id])->assertNotFound();
        $this->get(route('portal.licenses.show', $license))->assertDontSee('License history');
    }

    public function test_policies_allow_ownership_views_but_reserve_management_for_admins(): void
    {
        $admin = $this->administrator();
        $owner = $this->customer();
        $stranger = User::factory()->create();
        $license = $this->license($owner);

        foreach (['suspend', 'resume', 'revoke'] as $ability) {
            $this->assertTrue(Gate::forUser($admin)->allows($ability, $license));
            $this->assertFalse(Gate::forUser($owner->user)->allows($ability, $license));
            $this->assertFalse(Gate::forUser($stranger)->allows($ability, $license));
        }
        $this->assertTrue(Gate::forUser($owner->user)->allows('view', $license));
        $this->assertFalse(Gate::forUser($stranger)->allows('view', $license));
        foreach ([License::class, Customer::class, Product::class] as $model) {
            $this->assertTrue(Gate::forUser($admin)->allows('create', $model));
            $this->assertFalse(Gate::forUser($owner->user)->allows('create', $model));
            $this->assertFalse(Gate::forUser($admin)->allows('deleteAny', $model));
        }
        $this->assertFalse(Gate::forUser($admin)->allows('update', $license));
    }

    public function test_audit_events_cannot_be_edited_or_deleted_through_the_model(): void
    {
        $license = app(GrantManualLicense::class)->handle($this->administrator(), $this->grantData());
        $event = $license->events->sole();

        $this->assertThrows(fn () => $event->update(['reason' => 'Rewritten']), \LogicException::class);
        $this->assertThrows(fn () => $event->delete(), \LogicException::class);

        $this->assertSame('Manual customer grant', $event->fresh()->reason);
    }

    public function test_audit_insert_failure_rolls_back_the_entire_manual_grant(): void
    {
        $admin = $this->administrator();
        $data = $this->grantData();
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION pg_temp.reject_license_audit() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'Audit storage unavailable'; END $$;
            CREATE TRIGGER reject_license_audit BEFORE INSERT ON license_events
            FOR EACH ROW EXECUTE FUNCTION pg_temp.reject_license_audit();
            SQL);

        $this->assertThrows(fn () => app(GrantManualLicense::class)->handle($admin, $data), QueryException::class);

        $this->assertDatabaseCount('licenses', 0);
        $this->assertDatabaseCount('entitlements', 0);
        $this->assertDatabaseCount('license_events', 0);
    }

    public function test_status_change_requires_a_reason_and_rolls_back_if_audit_fails(): void
    {
        $admin = $this->administrator();
        $license = $this->license(attributes: ['status' => LicenseStatus::Active]);
        $change = app(ChangeLicenseStatus::class);
        $this->assertThrows(fn () => $change->handle($admin, $license, LicenseStatus::Suspended, ''), ValidationException::class);
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION pg_temp.reject_status_audit() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'Audit storage unavailable'; END $$;
            CREATE TRIGGER reject_status_audit BEFORE INSERT ON license_events
            FOR EACH ROW EXECUTE FUNCTION pg_temp.reject_status_audit();
            SQL);

        $this->assertThrows(fn () => $change->handle($admin, $license, LicenseStatus::Suspended, 'Support review'), QueryException::class);

        $this->assertSame(LicenseStatus::Active, $license->fresh()->status);
        $this->assertDatabaseCount('license_events', 0);
    }

    public function test_unverified_customers_cannot_open_license_details(): void
    {
        $customer = $this->customer(User::factory()->unverified()->create());
        $license = $this->license($customer);

        $this->actingAs($customer->user)->get(route('portal.licenses.show', $license))->assertRedirect(route('verification.notice'));
        $this->get('/portal/licenses/not-a-uuid')->assertNotFound();
    }

    public function test_livewire_updates_recheck_email_verification_after_initial_page_load(): void
    {
        $customer = $this->customer();
        $license = $this->license($customer);
        $page = $this->actingAs($customer->user)->get(route('portal.licenses.show', $license))->assertOk();
        preg_match('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
        $this->assertNotEmpty($matches);
        $snapshot = html_entity_decode($matches[1], ENT_QUOTES);
        $customer->user->forceFill(['email_verified_at' => null])->save();

        $this->withHeader('X-Livewire', 'true')->postJson(Livewire::getUpdateUri(), [
            'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => '$refresh', 'params' => []]]]],
        ])->assertForbidden();
    }

    public function test_license_ownership_is_rechecked_after_a_component_is_mounted(): void
    {
        $owner = $this->customer();
        $other = $this->customer();
        $license = $this->license($owner);
        $page = Livewire::actingAs($owner->user)->test(LicenseDetails::class, ['license' => $license->id])->assertOk();
        $license->update(['customer_id' => $other->id]);

        $page->call('$refresh')->assertNotFound();
    }
}
