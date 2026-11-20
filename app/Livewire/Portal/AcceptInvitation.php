<?php

namespace App\Livewire\Portal;

use App\Domain\Customers\Actions\AcceptInvitation as AcceptCustomerInvitation;
use App\Domain\Customers\Models\CustomerInvitation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AcceptInvitation extends Component
{
    #[Locked]
    public string $invitationId;

    #[Locked]
    public string $token;

    #[Locked]
    public string $invitedEmail;

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $invitation, string $token): void
    {
        $record = CustomerInvitation::find($invitation);
        abort_unless($record?->accepts($token), 404);
        $this->invitationId = $record->id;
        $this->token = $token;
        $this->invitedEmail = $record->email;
    }

    public function accept(AcceptCustomerInvitation $action): void
    {
        $user = $action->handle($this->invitationId, $this->token, [
            'password' => $this->password, 'password_confirmation' => $this->password_confirmation,
        ]);
        Auth::login($user);
        session()->regenerate();
        session()->put('password_hash_web', Auth::guard()->hashPasswordForCookie($user->getAuthPassword()));
        $this->reset('password', 'password_confirmation');
        $this->redirectRoute('verification.notice');
    }

    public function render(): View
    {
        return view('livewire.portal.accept-invitation')->layout('components.layouts.portal', ['title' => 'Create your account']);
    }
}
