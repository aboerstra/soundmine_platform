<?php

namespace App\Livewire\Portal;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ForgotPassword extends Component
{
    public string $email = '';

    public bool $sent = false;

    public function sendLink(): void
    {
        $this->email = Str::lower(trim($this->email));
        $this->validate(['email' => ['required', 'email', 'max:255']]);
        $key = 'password-recovery:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Please try again later.']);
        }
        RateLimiter::hit($key, 60);
        Password::sendResetLink(['email' => $this->email]);
        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.portal.forgot-password')->layout('components.layouts.portal', ['title' => 'Reset your password']);
    }
}
