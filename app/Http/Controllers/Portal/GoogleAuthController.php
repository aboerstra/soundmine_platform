<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Customers\Actions\AuthenticateGoogleCustomer;
use App\Domain\Customers\Data\GoogleProfile;
use App\Domain\Customers\Models\CustomerInvitation;
use App\Domain\Customers\Services\GoogleAuthentication;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class GoogleAuthController extends Controller
{
    public function __construct(private GoogleAuthentication $google, private AuthenticateGoogleCustomer $customers) {}

    public function login(Request $request): RedirectResponse
    {
        return $this->start($request, ['intent' => 'login']);
    }

    public function invitation(Request $request, CustomerInvitation $invitation, string $token): RedirectResponse
    {
        abort_unless($invitation->accepts($token), 404);

        return $this->start($request, [
            'intent' => 'invite', 'invitation_id' => $invitation->id,
            'invitation_token' => Crypt::encryptString($token),
        ]);
    }

    public function account(Request $request): View
    {
        $this->customers->assertCustomer($request->user());

        return view('portal.account', [
            'googleEnabled' => GoogleAuthentication::enabled(),
            'googleLinked' => $request->user()->socialIdentities()->where('provider', 'google')->exists(),
        ]);
    }

    public function link(Request $request): RedirectResponse
    {
        $this->customers->assertCustomer($request->user());
        $request->validate(['password' => ['required', 'string', 'max:1024', 'current_password:web']]);

        return $this->start($request, [
            'intent' => 'link', 'user_id' => $request->user()->id,
            'password_hash' => hash('sha256', $request->user()->getAuthPassword()),
        ]);
    }

    public function unlink(Request $request): RedirectResponse
    {
        $this->customers->assertCustomer($request->user());
        $request->validate(['password' => ['required', 'string', 'max:1024', 'current_password:web']]);
        $this->customers->unlink($request->user());
        $request->session()->forget(['google_auth', 'state', 'code_verifier']);

        return redirect()->route('portal.account')->with('status', 'Google sign-in disconnected. Your password still works.');
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(GoogleAuthentication::enabled(), 404);
        $context = $request->session()->pull('google_auth');
        $linking = ($context['intent'] ?? null) === 'link';
        $destination = $linking && $request->user() ? 'portal.account' : 'login';
        if (! is_array($context) || ! in_array($context['intent'] ?? null, ['login', 'invite', 'link'], true)
            || ($context['expires_at'] ?? 0) <= now()->timestamp
            || ($linking ? $request->user()?->id !== ($context['user_id'] ?? null) : $request->user() !== null)
            || ($linking && ! hash_equals($context['password_hash'] ?? '', hash('sha256', $request->user()->getAuthPassword())))) {
            $request->session()->forget(['state', 'code_verifier']);

            return redirect()->route($destination)->withErrors(['google' => 'This Google sign-in has expired or belongs to another session. Please start again.']);
        }
        if ($request->has('error')) {
            $request->session()->forget(['state', 'code_verifier']);

            return redirect()->route($destination)->withErrors(['google' => 'Google sign-in was cancelled. You can try again.']);
        }
        if (! is_string($request->input('code')) || strlen($request->input('code')) > 4096
            || ! is_string($request->input('state')) || strlen($request->input('state')) > 256) {
            $request->session()->forget(['state', 'code_verifier']);

            return redirect()->route($destination)->withErrors(['google' => 'The Google callback is invalid. Please start again.']);
        }
        try {
            $providerUser = $this->google->provider()->enablePKCE()->user();
        } catch (Exception $exception) {
            $request->session()->forget(['state', 'code_verifier']);
            Log::warning('Google authentication failed.', ['exception_type' => $exception::class]);

            return redirect()->route($destination)->withErrors(['google' => 'Google sign-in could not be completed. Please start again.']);
        }
        try {
            $profile = GoogleProfile::fromUser($providerUser);
            if ($linking) {
                $this->customers->link($request->user(), $profile);

                return redirect()->route('portal.account')->with('status', 'Google sign-in connected.');
            }
            $user = $context['intent'] === 'invite'
                ? $this->customers->acceptInvitation($context['invitation_id'], Crypt::decryptString($context['invitation_token']), $profile)
                : $this->customers->login($profile);
        } catch (ValidationException $exception) {
            return redirect()->route($destination)->withErrors($exception->errors());
        } catch (UniqueConstraintViolationException $exception) {
            return redirect()->route($destination)->withErrors(['google' => 'This Google account or invitation is already in use. Sign in with your password to manage the link.']);
        }
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('password_hash_web', Auth::guard()->hashPasswordForCookie($user->getAuthPassword()));

        return redirect()->route('portal');
    }

    private function start(Request $request, array $context): RedirectResponse
    {
        abort_unless(GoogleAuthentication::enabled(), 404);
        $request->session()->put('google_auth', $context + ['expires_at' => now()->addMinutes(10)->timestamp]);

        return $this->google->provider()->setScopes(['openid', 'email'])->enablePKCE()->with(['prompt' => 'select_account'])->redirect();
    }
}
