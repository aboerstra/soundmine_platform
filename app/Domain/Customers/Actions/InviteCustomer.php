<?php

namespace App\Domain\Customers\Actions;

use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerInvitation;
use App\Models\User;
use App\Notifications\CustomerInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InviteCustomer
{
    public function handle(User $actor, array $data): CustomerInvitation
    {
        Gate::forUser($actor)->authorize('create', Customer::class);
        $data['email'] = Str::lower(trim($data['email'] ?? ''));
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ])->validate();
        $token = Str::random(64);
        $invitation = DB::transaction(function () use ($actor, $validated, $token) {
            $invitation = CustomerInvitation::where('email', $validated['email'])->lockForUpdate()->first()
                ?? new CustomerInvitation(['email' => $validated['email']]);
            $invitation->fill([
                'name' => $validated['name'], 'token_hash' => hash('sha256', $token),
                'invited_by' => $actor->id, 'expires_at' => now()->addHours(48), 'accepted_at' => null,
            ])->save();

            return $invitation;
        });
        // Delivery happens after the business transaction. A failed delivery can be reissued by an administrator.
        Notification::route('mail', $invitation->email)->notify(new CustomerInvitationNotification($invitation->id, $token));

        return $invitation;
    }
}
