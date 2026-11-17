<?php

namespace Tests\Feature;

use App\Domain\Customers\Actions\AcceptInvitation;
use App\Domain\Customers\Actions\InviteCustomer;
use App\Livewire\Portal\AcceptInvitation as AcceptInvitationPage;
use App\Livewire\Portal\ForgotPassword;
use App\Livewire\Portal\Login;
use App\Livewire\Portal\ResetPassword;
use App\Models\User;
use App\Notifications\CustomerInvitationNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\CreatesDomainRecords;
use Tests\TestCase;

class CustomerOnboardingTest extends TestCase
{
    use CreatesDomainRecords, RefreshDatabase;

    private function invitationToken(): string
    {
        return Notification::sent(new AnonymousNotifiable, CustomerInvitationNotification::class)->last()->token;
    }

    public function test_administrator_invites_without_creating_an_account_or_storing_the_token(): void
    {
        Notification::fake();
        $this->freezeTime();
        $admin = $this->administrator();

        $invitation = app(InviteCustomer::class)->handle($admin, ['name' => 'Invited customer', 'email' => 'NEW@example.test']);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('customers', 0);
        $this->assertSame('new@example.test', $invitation->email);
        $this->assertSame(now()->addHours(48)->toIso8601String(), $invitation->expires_at->toIso8601String());
        Notification::assertSentOnDemand(CustomerInvitationNotification::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'new@example.test' && $notification->invitationId === $invitation->id && hash('sha256', $notification->token) === $invitation->token_hash);
        $this->assertNotSame($this->invitationToken(), $invitation->token_hash);
        $this->assertArrayNotHasKey('token_hash', $invitation->toArray());
    }

    public function test_customer_cannot_invite_another_customer(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->assertThrows(fn () => app(InviteCustomer::class)->handle($user, ['name' => 'Other', 'email' => 'other@example.test']), AuthorizationException::class);

        $this->assertDatabaseCount('customer_invitations', 0);
        Notification::assertNothingSent();
    }

    public function test_valid_invitation_creates_only_a_customer_account_and_sends_verification(): void
    {
        Notification::fake();
        $invitation = app(InviteCustomer::class)->handle($this->administrator(), ['name' => 'Invited customer', 'email' => 'new@example.test']);
        $token = $this->invitationToken();

        Livewire::test(AcceptInvitationPage::class, ['invitation' => $invitation->id, 'token' => $token])
            ->set('password', 'a-long-test-password')->set('password_confirmation', 'a-long-test-password')
            ->call('accept')->assertHasNoErrors()->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertSame('Invited customer', $user->customer->display_name);
        $this->assertNotNull($invitation->fresh()->accepted_at);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertThrows(fn () => app(AcceptInvitation::class)->handle($invitation->id, $token, ['password' => 'another-long-password', 'password_confirmation' => 'another-long-password']), ValidationException::class);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_expired_and_reissued_links_cannot_create_accounts(): void
    {
        Notification::fake();
        $this->freezeTime();
        $admin = $this->administrator();
        $invitation = app(InviteCustomer::class)->handle($admin, ['name' => 'Invited', 'email' => 'new@example.test']);
        $oldToken = $this->invitationToken();
        $this->travel(49)->hours();

        $this->get(route('invitation.accept', ['invitation' => $invitation->id, 'token' => $oldToken]))->assertNotFound();
        app(InviteCustomer::class)->handle($admin, ['name' => 'Invited', 'email' => 'new@example.test']);
        $this->get(route('invitation.accept', ['invitation' => $invitation->id, 'token' => $oldToken]))->assertNotFound();
        $this->get(route('invitation.accept', ['invitation' => $invitation->id, 'token' => $this->invitationToken()]))->assertOk();

        $this->assertDatabaseCount('customer_invitations', 1);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_invalid_password_does_not_consume_invitation(): void
    {
        Notification::fake();
        $invitation = app(InviteCustomer::class)->handle($this->administrator(), ['name' => 'Invited', 'email' => 'new@example.test']);

        Livewire::test(AcceptInvitationPage::class, ['invitation' => $invitation->id, 'token' => $this->invitationToken()])
            ->set('password', 'short')->set('password_confirmation', 'short')->call('accept')->assertHasErrors('password')->assertSee('12');

        $this->assertNull($invitation->fresh()->accepted_at);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_existing_account_cannot_be_invited_or_overwritten(): void
    {
        Notification::fake();
        $admin = $this->administrator();
        $user = User::factory()->create();

        $this->assertThrows(fn () => app(InviteCustomer::class)->handle($admin, ['name' => 'Overwrite', 'email' => strtoupper($user->email)]), ValidationException::class);

        $this->assertDatabaseCount('customer_invitations', 0);
        Notification::assertNothingSent();
    }

    public function test_verification_is_required_and_signed_for_the_authenticated_user(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get('/portal')->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertOk();
        $this->post(route('verification.send'))->assertRedirect();
        Notification::assertSentTo($user, VerifyEmail::class);
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->get($url)->assertRedirect(route('portal'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get('/portal')->assertOk();
    }

    public function test_verification_rejects_tampered_or_other_users_links(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $other->id, 'hash' => sha1($other->email)]);

        $this->actingAs($user)->get($url)->assertForbidden();
        $this->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_password_recovery_is_generic_and_delivers_a_real_broker_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        Livewire::test(ForgotPassword::class)->set('email', strtoupper($user->email))->call('sendLink')->assertHasNoErrors()->assertSee('If an account matches');
        Livewire::test(ForgotPassword::class)->set('email', 'unknown@example.test')->call('sendLink')->assertHasNoErrors()->assertSee('If an account matches');

        Notification::assertSentTo($user, ResetPasswordNotification::class, fn ($notification) => Password::tokenExists($user, $notification->token));
    }

    public function test_password_reset_rotates_password_and_remember_token_and_is_single_use(): void
    {
        $user = User::factory()->create();
        $oldRemember = $user->remember_token;
        $token = Password::createToken($user);

        Livewire::test(ResetPassword::class, ['token' => $token])->set('email', $user->email)
            ->set('password', 'new-long-test-password')->set('password_confirmation', 'new-long-test-password')
            ->call('resetPassword')->assertHasNoErrors()->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-long-test-password', $user->fresh()->password));
        $this->assertNotSame($oldRemember, $user->fresh()->remember_token);
        $this->assertFalse(Password::tokenExists($user->fresh(), $token));
        Livewire::test(ResetPassword::class, ['token' => $token])->set('email', $user->email)
            ->set('password', 'replayed-long-password')->set('password_confirmation', 'replayed-long-password')
            ->call('resetPassword')->assertHasErrors('email')->assertSee('invalid or expired');
    }

    public function test_expired_reset_token_is_rejected(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $this->travel(61)->minutes();

        Livewire::test(ResetPassword::class, ['token' => $token])->set('email', $user->email)
            ->set('password', 'new-long-test-password')->set('password_confirmation', 'new-long-test-password')
            ->call('resetPassword')->assertHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_recovery_is_rate_limited(): void
    {
        Notification::fake();
        for ($i = 0; $i < 5; $i++) {
            Livewire::test(ForgotPassword::class)->set('email', 'unknown@example.test')->call('sendLink');
        }

        Livewire::test(ForgotPassword::class)->set('email', 'unknown@example.test')->call('sendLink')->assertHasErrors('email')->assertSee('Too many attempts');

        Notification::assertNothingSent();
    }

    public function test_recovery_request_does_not_provision_an_unaccepted_invitation(): void
    {
        Notification::fake();
        $invitation = app(InviteCustomer::class)->handle($this->administrator(), ['name' => 'Invited', 'email' => 'new@example.test']);

        Livewire::test(ForgotPassword::class)->set('email', $invitation->email)->call('sendLink')->assertHasNoErrors();

        $this->assertDatabaseCount('customers', 0);
        Notification::assertSentOnDemandOnce(CustomerInvitationNotification::class);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_wrong_token_and_password_confirmation_leave_invitation_unconsumed(): void
    {
        Notification::fake();
        $invitation = app(InviteCustomer::class)->handle($this->administrator(), ['name' => 'Invited', 'email' => 'new@example.test']);
        $token = $this->invitationToken();
        $this->get(route('invitation.accept', ['invitation' => $invitation->id, 'token' => str_repeat('x', 64)]))->assertNotFound();

        Livewire::test(AcceptInvitationPage::class, ['invitation' => $invitation->id, 'token' => $token])
            ->set('password', 'a-long-test-password')->set('password_confirmation', 'mismatched-password')->call('accept')->assertHasErrors(['password' => 'confirmed']);

        $this->assertNull($invitation->fresh()->accepted_at);
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_reset_token_cannot_change_another_users_password(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $token = Password::createToken($owner);

        Livewire::test(ResetPassword::class, ['token' => $token])->set('email', $other->email)
            ->set('password', 'new-long-test-password')->set('password_confirmation', 'new-long-test-password')
            ->call('resetPassword')->assertHasErrors('email');

        $this->assertTrue(Hash::check('password', $other->fresh()->password));
    }

    public function test_password_reset_invalidates_an_existing_portal_session(): void
    {
        $user = User::factory()->create();
        Livewire::test(Login::class)->set('email', $user->email)->set('password', 'password')->call('login');
        $this->get('/portal')->assertOk();
        $token = Password::createToken($user);
        Livewire::test(ResetPassword::class, ['token' => $token])->set('email', $user->email)
            ->set('password', 'new-long-test-password')->set('password_confirmation', 'new-long-test-password')->call('resetPassword')->assertHasNoErrors();
        Auth::forgetGuards();

        $this->get('/portal')->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
