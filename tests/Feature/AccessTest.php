<?php

namespace Tests\Feature;

use App\Livewire\Portal\Dashboard;
use App\Livewire\Portal\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesDomainRecords;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use CreatesDomainRecords, RefreshDatabase;

    public function test_guests_must_sign_in_to_portal_and_administration(): void
    {
        $this->get('/portal')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/login')->assertOk();
        $this->get('/admin/login')->assertOk();
    }

    public function test_portal_displays_only_the_authenticated_customers_products(): void
    {
        $customer = $this->customer();
        $this->license($customer, $this->product('Owned product'));
        $this->license($this->customer(), $this->product('Private other product'));
        $this->actingAs($customer->user)->get('/portal')->assertOk()->assertSee('Owned product')->assertDontSee('Private other product');
        Livewire::actingAs($customer->user)->test(Dashboard::class)->assertSee('Owned product')->assertDontSee('Private other product');
    }

    public function test_user_without_customer_profile_sees_an_empty_portal(): void
    {
        $this->license();
        $this->actingAs(User::factory()->create())->get('/portal')->assertOk()->assertSee('No licenses are linked');
    }

    public function test_customer_cannot_access_admin_panel_even_in_local_environment(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_explicit_administrator_can_access_panel(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        $this->actingAs($user)->get('/admin')->assertOk();
    }

    public function test_admin_flag_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create();
        $user->fill(['is_admin' => true])->save();
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_customer_can_sign_in_and_sign_out(): void
    {
        $user = User::factory()->create(['password' => 'test-only-password']);
        Livewire::test(Login::class)->set('email', $user->email)->set('password', 'test-only-password')->call('login')->assertHasNoErrors()->assertRedirect('/portal');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_invalid_login_is_generic_and_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Login::class)->set('email', 'unknown@example.test')->set('password', 'incorrect')->call('login')->assertHasErrors('email')->assertSee('The supplied credentials are invalid.');
        }
        Livewire::test(Login::class)->set('email', 'unknown@example.test')->set('password', 'incorrect')->call('login')->assertSee('Too many attempts.');
        $this->assertGuest();
    }
}
