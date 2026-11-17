<section>
    <h1>Reset your password</h1>
    @if ($sent) <p role="status">If an account matches that email, a password reset link has been sent.</p> @endif
    <form wire:submit="sendLink">
        <label for="email">Email</label>
        <input id="email" type="email" wire:model="email" autocomplete="username" required>
        @error('email') <p role="alert">{{ $message }}</p> @enderror
        <button type="submit" wire:loading.attr="disabled">Send reset link</button>
    </form>
    <a href="{{ route('login') }}">Back to sign in</a>
</section>
