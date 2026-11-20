<?php

namespace Tests\Feature;

use App\Domain\Customers\Actions\InviteCustomer;
use App\Domain\Customers\Models\SocialIdentity;
use App\Livewire\Portal\Login;
use App\Livewire\Portal\ResetPassword;
use App\Models\User;
use App\Notifications\CustomerInvitationNotification;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\CreatesDomainRecords;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use CreatesDomainRecords, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.enabled' => true, 'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret', 'services.google.redirect' => 'http://localhost/auth/google/callback']);
    }

    private function fakeGoogle(string $email, string $subject = 'google-subject-1', array $attributes = []): void
    {
        Socialite::fake('google', GoogleUser::fake(array_replace(['id' => $subject, 'email' => $email, 'email_verified' => true], $attributes)));
    }

    private function identity(User $user, string $subject = 'google-subject-1'): SocialIdentity
    {
        return $user->socialIdentities()->create(['provider' => 'google', 'provider_subject' => $subject]);
    }

    private function invitation(): array
    {
        Notification::fake();
        $invitation = app(InviteCustomer::class)->handle($this->administrator(), ['name' => 'Invited customer', 'email' => 'invited@example.test']);
        $token = Notification::sent(new AnonymousNotifiable, CustomerInvitationNotification::class)->last()->token;

        return [$invitation, $token];
    }

    public function test_unconfigured_google_is_hidden_and_routes_fail_closed_without_affecting_password_login(): void
    {
        config(['services.google.client_secret' => null]);
        $user = $this->customer()->user;
        $this->get('/login')->assertOk()->assertDontSee('Sign in with Google');
        $this->post(route('google.login'))->assertNotFound();
        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertNotFound();
        config(['services.google.client_secret' => 'test-secret', 'services.google.enabled' => false]);
        $this->post(route('google.login'))->assertNotFound();
        Livewire::test(Login::class)->set('email', $user->email)->set('password', 'password')->call('login')->assertRedirect(route('portal'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_starts_stateful_pkce_flow_with_only_identity_scopes(): void
    {
        $response = $this->post(route('google.login'))->assertRedirect();
        $url = parse_url($response->headers->get('Location'));
        parse_str($url['query'], $query);

        $this->assertSame('accounts.google.com', $url['host']);
        $this->assertSame('openid email', $query['scope']);
        $this->assertSame('select_account', $query['prompt']);
        $this->assertSame(session('state'), $query['state']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertNotEmpty(session('code_verifier'));
        $response->assertSessionHas('google_auth.intent', 'login');
        $this->assertDatabaseCount('social_identities', 0);
        $this->get('/login')->assertSee('Sign in with Google');
        $this->get(route('google.login'))->assertStatus(405);
    }

    public function test_verified_customer_can_explicitly_link_google_after_password_confirmation(): void
    {
        $user = $this->customer()->user;
        $license = $this->license($user->customer);
        $this->fakeGoogle($user->email);
        $this->actingAs($user)->get(route('portal.account'))->assertOk()->assertSee('Connect Google');
        $this->post(route('google.link'), ['password' => 'password'])->assertRedirect();
        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertRedirect(route('portal.account'))->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('social_identities', ['user_id' => $user->id, 'provider' => 'google', 'provider_subject' => 'google-subject-1']);
        $this->assertSame($license->customer_id, $user->fresh()->customer->id);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('licenses', 1);
        $this->assertArrayNotHasKey('provider_subject', SocialIdentity::sole()->toArray());
        $this->get(route('portal.account'))->assertOk()->assertSee('Disconnect Google');
    }

    public function test_link_and_unlink_require_the_current_password(): void
    {
        $user = $this->customer()->user;
        $this->actingAs($user)->post(route('google.link'), ['password' => 'incorrect'])->assertSessionHasErrors('password')->assertSessionMissing('google_auth');
        $identity = $this->identity($user);
        $this->delete(route('google.unlink'), ['password' => 'incorrect'])->assertSessionHasErrors('password');
        $this->assertModelExists($identity);
        $this->assertDatabaseCount('social_identities', 1);
    }

    public static function ineligibleAccounts(): array
    {
        return ['administrator' => [true, true, true], 'unverified' => [false, false, true], 'without customer' => [false, true, false]];
    }

    #[DataProvider('ineligibleAccounts')]
    public function test_ineligible_accounts_cannot_link_or_manage_google(bool $admin, bool $verified, bool $customer): void
    {
        $user = User::factory()->create(['is_admin' => $admin, 'email_verified_at' => $verified ? now() : null]);
        if ($customer) {
            $this->customer($user);
        }
        $response = $this->actingAs($user)->post(route('google.link'), ['password' => 'password']);
        if ($verified) {
            $response->assertForbidden();
            $this->get(route('portal.account'))->assertForbidden();
        } else {
            $response->assertRedirect(route('verification.notice'));
        }
        $this->assertDatabaseCount('social_identities', 0);
    }

    public function test_google_does_not_link_an_existing_customer_by_matching_email(): void
    {
        $user = $this->customer()->user;
        $this->fakeGoogle($user->email);
        $this->post(route('google.login'));

        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertRedirect(route('login'))->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseCount('social_identities', 0);
    }

    public function test_only_linked_subject_signs_into_existing_customer_without_changing_local_email(): void
    {
        $user = $this->customer()->user;
        $this->identity($user);
        $this->fakeGoogle('changed-google-email@example.test');
        $this->post(route('google.login'));
        $oldSession = session()->getId();

        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertRedirect(route('portal'))->assertSessionMissing('google_auth');

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertSame($user->email, $user->fresh()->email);
        $this->get('/portal')->assertOk();
        $this->get('/admin')->assertForbidden();
    }

    public function test_administrator_cannot_sign_in_through_a_previously_linked_google_identity(): void
    {
        $user = $this->customer()->user;
        $this->identity($user);
        $user->forceFill(['is_admin' => true])->save();
        $this->fakeGoogle($user->email);
        $this->post(route('google.login'));

        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertForbidden();

        $this->assertGuest();
    }

    public function test_invitation_with_matching_verified_google_email_creates_only_a_verified_customer(): void
    {
        [$invitation, $token] = $this->invitation();
        $this->fakeGoogle(strtoupper($invitation->email));
        $this->get(route('invitation.accept', [$invitation->id, $token]))->assertOk()->assertSee('Accept invitation with Google');
        $this->post(route('google.invitation', [$invitation->id, $token]))->assertRedirect();
        $this->assertStringNotContainsString($token, json_encode(session('google_auth')));

        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertRedirect(route('portal'))->assertSessionHasNoErrors();

        $user = User::where('email', $invitation->email)->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertFalse($user->is_admin);
        $this->assertFalse(Hash::check('password', $user->password));
        $this->assertSame($invitation->name, $user->customer->display_name);
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->assertDatabaseCount('social_identities', 1);
        $this->assertDatabaseCount('licenses', 0);
        Notification::assertNothingSentTo($user);
        $this->get('/portal')->assertOk();
        $reset = Password::createToken($user);
        Livewire::test(ResetPassword::class, ['token' => $reset])->set('email', $user->email)
            ->set('password', 'new-long-test-password')->set('password_confirmation', 'new-long-test-password')->call('resetPassword')->assertHasNoErrors();
        $this->assertTrue(Hash::check('new-long-test-password', $user->fresh()->password));
    }

    public function test_google_cannot_register_without_an_invitation(): void
    {
        $this->fakeGoogle('unknown@example.test');
        $this->post(route('google.login'));

        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertRedirect(route('login'))->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('customers', 0);
    }

    #[TestWith([false])]
    #[TestWith(['true'])]
    #[TestWith([null])]
    public function test_google_profile_must_explicitly_confirm_a_verified_email(mixed $verified): void
    {
        [$invitation, $token] = $this->invitation();
        $this->fakeGoogle($invitation->email, attributes: ['email_verified' => $verified]);
        $this->post(route('google.invitation', [$invitation->id, $token]));

        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertNull($invitation->fresh()->accepted_at);
        $this->assertDatabaseCount('social_identities', 0);
    }

    public function test_wrong_google_email_cannot_accept_or_consume_an_invitation(): void
    {
        [$invitation, $token] = $this->invitation();
        $this->fakeGoogle('other@example.test');
        $this->post(route('google.invitation', [$invitation->id, $token]));

        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertSessionHasErrors('google');

        $this->assertNull($invitation->fresh()->accepted_at);
        $this->assertDatabaseCount('customers', 0);
    }

    #[TestWith(['expired'])]
    #[TestWith(['reissued'])]
    #[TestWith(['accepted'])]
    public function test_invitation_is_rechecked_after_the_google_redirect(string $change): void
    {
        [$invitation, $token] = $this->invitation();
        $this->fakeGoogle($invitation->email);
        $this->post(route('google.invitation', [$invitation->id, $token]));
        $invitation->update(match ($change) {
            'expired' => ['expires_at' => now()->subMinute()],
            'reissued' => ['token_hash' => hash('sha256', 'replacement-token')],
            'accepted' => ['accepted_at' => now()],
        });

        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertSessionHasErrors('google');

        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('social_identities', 0);
    }

    public function test_invitation_never_overwrites_an_account_created_while_google_was_open(): void
    {
        [$invitation, $token] = $this->invitation();
        $this->fakeGoogle($invitation->email);
        $this->post(route('google.invitation', [$invitation->id, $token]));
        $existing = User::factory()->create(['email' => $invitation->email]);

        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertSessionHasErrors('google');

        $this->assertSame($existing->name, $existing->fresh()->name);
        $this->assertNull($invitation->fresh()->accepted_at);
        $this->assertDatabaseCount('social_identities', 0);
    }

    public function test_a_link_cannot_move_between_customers_or_replace_a_different_google_subject(): void
    {
        $owner = $this->customer()->user;
        $other = $this->customer()->user;
        $this->identity($owner);
        $this->fakeGoogle($other->email);
        $this->actingAs($other)->post(route('google.link'), ['password' => 'password']);
        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertSessionHasErrors('google');
        $this->assertSame($owner->id, SocialIdentity::sole()->user_id);
        $this->fakeGoogle($owner->email, 'replacement-subject');
        $this->actingAs($owner)->post(route('google.link'), ['password' => 'password']);
        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertSessionHasErrors('google');
        $this->assertSame('google-subject-1', SocialIdentity::sole()->provider_subject);
    }

    public function test_linking_requires_the_matching_google_email_even_for_a_signed_in_customer(): void
    {
        $user = $this->customer()->user;
        $this->fakeGoogle('different@example.test');
        $this->actingAs($user)->post(route('google.link'), ['password' => 'password']);

        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertSessionHasErrors('google');

        $this->assertDatabaseCount('social_identities', 0);
    }

    public function test_callback_requires_live_context_and_rejects_replay(): void
    {
        $this->freezeTime();
        $user = $this->customer()->user;
        $this->identity($user);
        $this->fakeGoogle($user->email);
        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertSessionHasErrors('google');
        $this->post(route('google.login'));
        $this->travel(10)->minutes();
        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->post(route('google.login'));
        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertRedirect(route('portal'));
        $this->post('/logout');
        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_link_callback_cannot_switch_to_a_different_logged_in_customer(): void
    {
        $user = $this->customer()->user;
        $other = $this->customer()->user;
        $this->fakeGoogle($user->email);
        $this->actingAs($user)->post(route('google.link'), ['password' => 'password']);
        $this->actingAs($other);

        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertSessionHasErrors('google');

        $this->assertAuthenticatedAs($other);
        $this->assertDatabaseCount('social_identities', 0);
    }

    public function test_cancelled_oauth_returns_a_safe_message_without_consuming_an_invitation(): void
    {
        [$invitation, $token] = $this->invitation();
        $this->post(route('google.invitation', [$invitation->id, $token]));
        Socialite::fake('google', fn () => throw new \RuntimeException('Provider should not be called.'));

        $this->get(route('google.callback', ['error' => 'access_denied']))->assertSessionHasErrors('google')->assertSessionMissing('google_auth');

        $this->assertNull($invitation->fresh()->accepted_at);
        $this->assertDatabaseCount('social_identities', 0);
    }

    public function test_real_socialite_flow_validates_state_and_uses_pkce_without_live_google_requests(): void
    {
        $user = $this->customer()->user;
        $this->identity($user);
        $responses = new MockHandler([
            new Response(200, [], json_encode(['access_token' => 'test-provider-token', 'expires_in' => 3600, 'scope' => 'openid email'])),
            new Response(200, [], json_encode(['sub' => 'google-subject-1', 'email' => $user->email, 'email_verified' => true])),
        ]);
        $history = [];
        $stack = HandlerStack::create($responses);
        $stack->push(Middleware::history($history));
        Socialite::driver('google')->setHttpClient(new Client(['handler' => $stack]));
        $this->post(route('google.login'));
        $state = session('state');
        $verifier = session('code_verifier');

        $this->get(route('google.callback', ['state' => $state, 'code' => 'test-code']))->assertRedirect(route('portal'));

        $this->assertAuthenticatedAs($user);
        $this->assertCount(2, $history);
        $this->assertSame('https://www.googleapis.com/oauth2/v4/token', (string) $history[0]['request']->getUri());
        parse_str((string) $history[0]['request']->getBody(), $body);
        $this->assertSame($verifier, $body['code_verifier']);
        $this->assertSame('test-code', $body['code']);
        $this->assertSame('Bearer test-provider-token', $history[1]['request']->getHeaderLine('Authorization'));
        $this->assertDatabaseCount('social_identities', 1);
        $this->assertSame(['id', 'user_id', 'provider', 'created_at', 'updated_at'], array_keys(SocialIdentity::sole()->toArray()));
    }

    public function test_invalid_oauth_state_is_rejected_before_any_google_network_request(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([]));
        $stack->push(Middleware::history($history));
        Socialite::driver('google')->setHttpClient(new Client(['handler' => $stack]));
        $this->post(route('google.login'));

        $this->get(route('google.callback', ['state' => 'forged-state', 'code' => 'test-code']))->assertRedirect(route('login'))->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertSame([], $history);
        $this->assertDatabaseCount('social_identities', 0);
    }

    public function test_customer_can_disconnect_only_their_link_and_cannot_use_it_for_future_sign_in(): void
    {
        $user = $this->customer()->user;
        $other = $this->customer()->user;
        $this->identity($user);
        $this->identity($other, 'other-subject');
        $this->actingAs($user)->delete(route('google.unlink'), ['password' => 'password', 'user_id' => $other->id])->assertRedirect(route('portal.account'));
        $this->assertSame($other->id, SocialIdentity::sole()->user_id);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->post('/logout');
        $this->fakeGoogle($user->email);
        $this->post(route('google.login'));

        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertSessionHasErrors('google');

        $this->assertGuest();
    }

    public function test_google_transport_failure_is_safe_and_does_not_create_a_customer(): void
    {
        [$invitation, $token] = $this->invitation();
        $stack = HandlerStack::create(new MockHandler([new Response(503, [], 'provider error')]));
        Socialite::driver('google')->setHttpClient(new Client(['handler' => $stack]));
        $this->post(route('google.invitation', [$invitation->id, $token]));

        $this->get(route('google.callback', ['state' => session('state'), 'code' => 'test-code']))->assertRedirect(route('login'))->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertNull($invitation->fresh()->accepted_at);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('social_identities', 0);
    }

    public function test_guest_login_callback_does_not_replace_an_account_that_signed_in_in_another_tab(): void
    {
        $owner = $this->customer()->user;
        $other = $this->customer()->user;
        $this->identity($owner);
        $this->fakeGoogle($owner->email);
        $this->post(route('google.login'));
        $this->actingAs($other);

        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertSessionHasErrors('google');

        $this->assertAuthenticatedAs($other);
    }

    public function test_pending_link_is_invalidated_by_password_change(): void
    {
        $user = $this->customer()->user;
        $this->fakeGoogle($user->email);
        $this->actingAs($user)->post(route('google.link'), ['password' => 'password']);
        $user->update(['password' => 'new-long-test-password']);
        Auth::forgetGuards();

        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertRedirect(route('login'));

        $this->assertDatabaseCount('social_identities', 0);
    }

    public function test_an_existing_google_subject_cannot_accept_another_customer_invitation(): void
    {
        [$invitation, $token] = $this->invitation();
        $owner = $this->customer()->user;
        $this->identity($owner);
        $this->fakeGoogle($invitation->email);
        $this->post(route('google.invitation', [$invitation->id, $token]));

        $this->get(route('google.callback', ['state' => 'test-state', 'code' => 'test-code']))->assertSessionHasErrors('google');

        $this->assertSame($owner->id, SocialIdentity::sole()->user_id);
        $this->assertNull($invitation->fresh()->accepted_at);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_google_login_attempts_are_rate_limited(): void
    {
        Socialite::fake('google');
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('google.login'))->assertRedirect();
        }

        $this->post(route('google.login'))->assertTooManyRequests()->assertHeader('Retry-After');

        $this->assertDatabaseCount('social_identities', 0);
    }

    #[TestWith([['code' => ['invalid'], 'state' => 'state']])]
    #[TestWith([['state' => 'state']])]
    #[TestWith([['code' => 'code', 'state' => ['invalid']]])]
    public function test_malformed_callback_is_rejected_without_consuming_an_invitation(array $query): void
    {
        [$invitation, $token] = $this->invitation();
        $this->fakeGoogle($invitation->email);
        $this->post(route('google.invitation', [$invitation->id, $token]));

        $this->get(route('google.callback', $query))->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertNull($invitation->fresh()->accepted_at);
        $this->assertDatabaseCount('social_identities', 0);
    }
}
