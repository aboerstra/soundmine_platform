<?php

namespace App\Livewire\Portal;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public function login(): void
    {
        $this->email = Str::lower(trim($this->email));
        $this->validate(['email' => 'required|email|max:255', 'password' => 'required|string|max:1024']);
        $key = 'portal-login:'.hash('sha256', mb_strtolower($this->email).'|'.request()->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Please try again later.']);
        }
        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            RateLimiter::hit($key, 60);
            $this->reset('password');
            throw ValidationException::withMessages(['email' => 'The supplied credentials are invalid.']);
        }
        RateLimiter::clear($key);
        session()->regenerate();
        session()->put('password_hash_web', Auth::guard()->hashPasswordForCookie(Auth::user()->getAuthPassword()));
        $this->reset('password');
        $this->redirectIntended(route('portal'));
    }

    public function render(): View
    {
        return view('livewire.portal.login')->layout('components.layouts.portal', ['title' => 'Sign in']);
    }
}
