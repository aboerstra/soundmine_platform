<?php

namespace Tests\Feature;

use App\Domain\Customers\Models\CustomerInvitation;
use App\Domain\Licensing\Enums\LicenseStatus;
use App\Domain\Licensing\Models\License;
use App\Filament\Resources\CustomerInvitations\Pages\ManageCustomerInvitations;
use App\Filament\Resources\Customers\Pages\ManageCustomers;
use App\Filament\Resources\Licenses\Pages\ManageLicenses;
use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Notifications\CustomerInvitationNotification;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\CreatesDomainRecords;
use Tests\TestCase;

class AdminResourcesTest extends TestCase
{
    use CreatesDomainRecords, RefreshDatabase;

    public function test_administrators_can_load_all_management_screens(): void
    {
        $admin = $this->administrator();
        $this->actingAs($admin);
        foreach (['customers', 'customer-invitations', 'products', 'licenses'] as $path) {
            $this->get('/admin/'.$path)->assertOk();
        }
    }

    public function test_customer_is_denied_management_screen_and_livewire_mount(): void
    {
        $this->actingAs($this->customer()->user)->get('/admin/licenses')->assertForbidden();
        Livewire::test(ManageLicenses::class)->assertForbidden();
    }

    public function test_admin_can_invite_and_reissue_through_filament(): void
    {
        Notification::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->administrator());

        Livewire::test(ManageCustomers::class)->callAction('invite', data: ['name' => 'New customer', 'email' => 'new@example.test'])->assertHasNoActionErrors();

        $invitation = CustomerInvitation::sole();
        $oldDigest = $invitation->token_hash;
        Livewire::test(ManageCustomerInvitations::class)->callAction(TestAction::make('resend')->table($invitation))->assertHasNoActionErrors();
        $this->assertNotSame($oldDigest, $invitation->fresh()->token_hash);
        Notification::assertSentOnDemandTimes(CustomerInvitationNotification::class, 2);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_admin_can_create_product_and_edit_customer_display_name(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->administrator());
        $customer = $this->customer();

        Livewire::test(ManageProducts::class)->callAction('create', data: ['name' => 'Future product', 'slug' => 'future-product', 'description' => 'Another Soundmine product', 'is_active' => true])->assertHasNoActionErrors();
        Livewire::test(ManageCustomers::class)->callAction(TestAction::make('edit')->table($customer), data: ['display_name' => 'Updated customer'])->assertHasNoActionErrors();

        $this->assertDatabaseHas('products', ['slug' => 'future-product', 'is_active' => true]);
        $this->assertSame('Updated customer', $customer->fresh()->display_name);
    }

    public function test_filament_grant_and_status_actions_use_the_audited_domain_workflow(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $admin = $this->administrator();
        $this->actingAs($admin);
        $customer = $this->customer();
        $product = $this->product();
        $product->update(['is_active' => true]);

        Livewire::test(ManageLicenses::class)->callAction('create', data: [
            'customer_id' => $customer->id, 'product_id' => $product->id, 'max_activations' => 2,
            'starts_at' => '2026-11-17 12:00:00', 'expires_at' => null, 'reason' => 'Support grant',
            'entitlements' => [['key' => 'edition', 'value' => 'full']],
        ])->assertHasNoActionErrors();

        $license = License::sole();
        $this->assertSame('full', $license->entitlements->sole()->value);
        $this->assertSame($admin->id, $license->events->sole()->actor_id);
        Livewire::test(ManageLicenses::class)->callAction(TestAction::make('suspend')->table($license), data: ['reason' => 'Under investigation'])->assertHasNoActionErrors();
        $this->assertSame(LicenseStatus::Suspended, $license->fresh()->status);
        $this->get('/admin/licenses/'.$license->id)->assertOk()->assertSee('Support grant')->assertSee('Under investigation');
        $this->assertDatabaseCount('license_events', 2);
    }

    public function test_grant_form_requires_reason_without_creating_a_license(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->administrator());
        $customer = $this->customer();
        $product = $this->product();
        $product->update(['is_active' => true]);

        Livewire::test(ManageLicenses::class)->callAction('create', data: [
            'customer_id' => $customer->id, 'product_id' => $product->id, 'max_activations' => 2,
            'starts_at' => '2026-11-17 12:00:00', 'reason' => '',
        ])->assertHasActionErrors(['reason' => 'required']);

        $this->assertDatabaseCount('licenses', 0);
        $this->assertDatabaseCount('license_events', 0);
    }
}
