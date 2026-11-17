<?php

namespace App\Domain\Customers\Actions;

use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerInvitation;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AcceptInvitation
{
    public function handle(string $invitationId, string $token, array $data): User
    {
        $validated = Validator::make($data, ['password' => ['required', 'string', Password::min(12), 'confirmed', 'max:255']])->validate();
        $user = DB::transaction(function () use ($invitationId, $token, $validated) {
            $invitation = CustomerInvitation::lockForUpdate()->find($invitationId);
            if (! $invitation?->accepts($token) || User::where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages(['token' => 'This invitation is invalid, expired, or already used.']);
            }
            $user = User::create(['name' => $invitation->name, 'email' => $invitation->email, 'password' => $validated['password']]);
            Customer::create(['user_id' => $user->id, 'display_name' => $invitation->name]);
            $invitation->update(['accepted_at' => now()]);

            return $user;
        });
        event(new Registered($user));

        return $user;
    }
}
