<?php

namespace App\Livewire\Portal;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ResetPassword extends Component
{
    #[Locked]
    public string $token;

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function resetPassword(): void
    {
        $this->email = Str::lower(trim($this->email));
        $this->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', PasswordRule::min(12), 'confirmed', 'max:255'],
        ]);
        $key = 'password-reset:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Please try again later.']);
        }
        RateLimiter::hit($key, 60);
        $status = Password::reset([
            'email' => $this->email, 'token' => $this->token,
            'password' => $this->password, 'password_confirmation' => $this->password_confirmation,
        ], function ($user, string $password): void {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => 'This reset link is invalid or expired. Please request another.']);
        }
        $this->reset('password', 'password_confirmation');
        session()->flash('status', 'Your password has been reset. Please sign in.');
        $this->redirectRoute('login');
    }

    public function render(): View
    {
        return view('livewire.portal.reset-password')->layout('components.layouts.portal', ['title' => 'Choose a new password']);
    }
}
