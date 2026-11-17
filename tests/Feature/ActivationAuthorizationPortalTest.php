<?php

namespace Tests\Feature;

use App\Domain\Activations\Models\ActivationAuthorization;
use App\Livewire\Portal\LicenseDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Concerns\CreatesActivationRecords;
use Tests\TestCase;

class ActivationAuthorizationPortalTest extends TestCase
{
    use CreatesActivationRecords, RefreshDatabase;

    public function test_verified_owner_can_generate_a_bound_authorization_from_the_portal(): void
    {
        $license = $this->activationLicense();

        $page = Livewire::actingAs($license->customer->user)->test(LicenseDetails::class, ['license' => $license->id])
            ->set('machineHash', str_repeat('a', 64))->set('platform', 'windows')->call('issueAuthorization')->assertHasNoErrors();

        $code = $page->get('authorizationCode');
        $page->assertSee($code);
        $grant = ActivationAuthorization::sole();
        $this->assertSame(hash('sha256', $code), $grant->token_hash);
        $this->assertSame($license->product_id, $grant->product_id);
        $this->assertSame('windows', $grant->platform);
        $this->assertDatabaseCount('activations', 0);
    }

    public function test_installation_authorization_policy_never_grants_an_administrator_other_customers_licenses(): void
    {
        $license = $this->activationLicense();
        $owner = $license->customer->user;
        $this->assertTrue(Gate::forUser($owner)->allows('authorizeInstallation', $license));
        $this->assertFalse(Gate::forUser($this->administrator())->allows('authorizeInstallation', $license));
        $this->assertFalse(Gate::forUser($this->customer()->user)->allows('authorizeInstallation', $license));
        $owner->forceFill(['email_verified_at' => null])->save();
        $this->assertFalse(Gate::forUser($owner)->allows('authorizeInstallation', $license));
    }

    public function test_invalid_installation_data_and_inactive_license_create_no_code(): void
    {
        $license = $this->activationLicense();
        $page = Livewire::actingAs($license->customer->user)->test(LicenseDetails::class, ['license' => $license->id]);
        $page->set('machineHash', 'not-a-digest')->call('issueAuthorization')->assertHasErrors('machine_hash');
        $license->update(['status' => 'revoked']);

        $page->set('machineHash', str_repeat('a', 64))->call('issueAuthorization')->assertHasErrors('authorization')->assertSee('not active');

        $this->assertDatabaseCount('activation_authorizations', 0);
    }

    public function test_changed_ownership_is_rechecked_before_issuing_a_code(): void
    {
        $license = $this->activationLicense();
        $page = Livewire::actingAs($license->customer->user)->test(LicenseDetails::class, ['license' => $license->id]);
        $page->set('machineHash', str_repeat('a', 64));
        $license->update(['customer_id' => $this->customer()->id]);

        $page->call('issueAuthorization')->assertNotFound();

        $this->assertDatabaseCount('activation_authorizations', 0);
    }
}
