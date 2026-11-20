<?php

namespace App\Domain\Customers\Actions;

use App\Domain\Customers\Data\GoogleProfile;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerInvitation;
use App\Domain\Customers\Models\SocialIdentity;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class AuthenticateGoogleCustomer
{
    public function login(GoogleProfile $profile): User
    {
        $identity = SocialIdentity::where('provider', 'google')->where('provider_subject', $profile->subject)->first();
        if (! $identity) {
            throw ValidationException::withMessages(['google' => 'Sign in with your password to link Google, or use your customer invitation.']);
        }

        return DB::transaction(function () use ($identity) {
            $user = User::lockForUpdate()->findOrFail($identity->user_id);
            $this->assertCustomer($user);
            if (! $user->socialIdentities()->whereKey($identity->id)->exists()) {
                throw ValidationException::withMessages(['google' => 'Google is no longer linked to this account.']);
            }

            return $user;
        });
    }

    public function link(User $user, GoogleProfile $profile): void
    {
        DB::transaction(function () use ($user, $profile) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $this->assertCustomer($user);
            if ($user->email !== $profile->email) {
                throw ValidationException::withMessages(['google' => 'Choose the Google account with the same email as your Soundmine account.']);
            }
            $identity = SocialIdentity::where('provider', 'google')->where('provider_subject', $profile->subject)->first();
            $existing = $user->socialIdentities()->where('provider', 'google')->first();
            if (($identity && $identity->user_id !== $user->id) || ($existing && $existing->provider_subject !== $profile->subject)) {
                throw ValidationException::withMessages(['google' => 'This Google account cannot be linked. Disconnect the existing link first, if applicable.']);
            }
            $user->socialIdentities()->firstOrCreate(['provider' => 'google'], ['provider_subject' => $profile->subject]);
        });
    }

    public function acceptInvitation(string $id, #[SensitiveParameter] string $token, GoogleProfile $profile): User
    {
        $user = DB::transaction(function () use ($id, $token, $profile) {
            $invitation = CustomerInvitation::lockForUpdate()->find($id);
            if (! $invitation?->accepts($token) || $invitation->email !== $profile->email
                || User::where('email', $invitation->email)->exists()
                || SocialIdentity::where('provider', 'google')->where('provider_subject', $profile->subject)->exists()) {
                throw ValidationException::withMessages(['google' => 'Use a valid, unused invitation and its matching verified Google email.']);
            }
            $user = User::create(['name' => $invitation->name, 'email' => $invitation->email, 'password' => Str::random(64)]);
            $user->forceFill(['email_verified_at' => now()])->save();
            Customer::create(['user_id' => $user->id, 'display_name' => $invitation->name]);
            $user->socialIdentities()->create(['provider' => 'google', 'provider_subject' => $profile->subject]);
            $invitation->update(['accepted_at' => now()]);

            return $user;
        });
        event(new Registered($user));
        event(new Verified($user));

        return $user;
    }

    public function unlink(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $this->assertCustomer($user);
            $user->socialIdentities()->where('provider', 'google')->delete();
        });
    }

    public function assertCustomer(User $user): void
    {
        abort_unless(! $user->is_admin && $user->hasVerifiedEmail() && $user->customer()->exists(), 403);
    }
}
