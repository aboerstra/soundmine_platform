<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Livewire\Portal\Dashboard;
use App\Livewire\Portal\LicenseDetails;
use App\Livewire\Portal\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesActivationRecords;
use Tests\TestCase;

class PortalUsabilityTest extends TestCase
{
    use CreatesActivationRecords, RefreshDatabase;

    public function test_search_and_pagination_only_expose_the_current_customers_products(): void
    {
        $customer = $this->customer();
        $this->license($customer, $this->product('Harmonic Conductor'));
        for ($index = 0; $index < 12; $index++) {
            $this->license($customer, $this->product('Studio edition '.$index));
        }
        $this->license($this->customer(), $this->product('Private Harmonic Conductor'));

        $page = Livewire::actingAs($customer->user)->test(Dashboard::class)
            ->assertViewHas('licenses', fn ($licenses) => $licenses->total() === 13 && $licenses->count() === 12)
            ->assertDontSee('Private Harmonic Conductor');
        $page->call('nextPage')->assertViewHas('licenses', fn ($licenses) => $licenses->currentPage() === 2 && $licenses->count() === 1);
        $page->set('search', 'HARMONIC')->assertSee('Harmonic Conductor')->assertDontSee('Private Harmonic Conductor')
            ->assertViewHas('licenses', fn ($licenses) => $licenses->currentPage() === 1 && $licenses->total() === 1);
        $page->set('search', 'does-not-exist')->assertSee('No matching products');
        $page->set('search', '')->assertViewHas('licenses', fn ($licenses) => $licenses->total() === 13);
    }

    public function test_search_treats_percent_and_underscore_as_literal_characters(): void
    {
        $customer = $this->customer();
        $this->license($customer, $this->product('100% Studio'));
        $this->license($customer, $this->product('Other Studio'));
        Livewire::actingAs($customer->user)->test(Dashboard::class)->set('search', '%')->assertSee('100% Studio')->assertDontSee('Other Studio');
    }

    public function test_expired_active_license_displays_expiry_and_prevents_unavailable_authorization_form(): void
    {
        $license = $this->activationLicense(attributes: ['expires_at' => now()->subMinute(), 'starts_at' => now()->subDay()]);

        $this->actingAs($license->customer->user)->get(route('portal.licenses.show', $license))->assertOk()
            ->assertSee('Expired')->assertSee('Authorization unavailable')->assertSee('This license has expired.')
            ->assertDontSee('Generate authorization code');
    }

    public function test_disabled_activation_does_not_offer_a_working_authorization_button(): void
    {
        $license = $this->activationLicense();
        config(['soundmine.activations.enabled' => false]);

        Livewire::actingAs($license->customer->user)->test(LicenseDetails::class, ['license' => $license->id])
            ->assertSee('Online activation is currently unavailable.')->assertDontSee('Generate authorization code');
    }

    public function test_code_preview_is_cleared_when_its_installation_binding_changes(): void
    {
        $license = $this->activationLicense();
        $page = Livewire::actingAs($license->customer->user)->test(LicenseDetails::class, ['license' => $license->id])
            ->set('machineHash', str_repeat('a', 64))->call('issueAuthorization')->assertHasNoErrors()->assertSee('Your authorization code is ready');

        $page->set('platform', 'windows')->assertSet('authorizationCode', null)->assertDontSee('Your authorization code is ready');
        $page->call('issueAuthorization')->assertHasNoErrors();
        $page->set('machineHash', str_repeat('b', 64))->assertSet('authorizationCode', null);
    }

    public function test_invalid_login_exposes_an_associated_field_error_and_recovery_route(): void
    {
        Livewire::test(Login::class)->set('email', 'wrong@example.test')->set('password', 'incorrect')->call('login')
            ->assertHasErrors('email')->assertSeeHtml('id="email-error"')->assertSeeHtml('aria-invalid="true"')->assertSee('Forgot your password?');
    }

    public function test_catalog_name_migration_preserves_product_identity_and_licenses(): void
    {
        $product = Product::create(['slug' => 'rhythm-conductor', 'name' => 'Rhythm Conductor', 'is_active' => true]);
        $license = $this->license(product: $product);
        $migration = require database_path('migrations/2026_11_20_045957_rename_rhythm_conductor_display_name.php');

        $migration->up();

        $this->assertSame('TheSoundMine Pattern Conductor', $product->fresh()->name);
        $this->assertSame('rhythm-conductor', $product->fresh()->slug);
        $this->assertTrue($product->fresh()->is_active);
        $this->assertSame($product->id, $license->fresh()->product_id);
        $migration->down();
        $this->assertSame('TheSoundMine Pattern Conductor', $product->fresh()->name);
    }

    public function test_catalog_seed_uses_the_brand_name_without_renaming_existing_custom_products(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseHas('products', ['slug' => 'rhythm-conductor', 'name' => 'TheSoundMine Pattern Conductor', 'is_active' => false]);
        $this->assertDatabaseCount('products', 1);
    }
}
