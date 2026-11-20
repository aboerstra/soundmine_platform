<section>
    <h1>Sign in to Soundmine</h1>
    @if (session('status')) <p role="status">{{ session('status') }}</p> @endif
    @error('google') <p role="alert">{{ $message }}</p> @enderror
    @if (\App\Domain\Customers\Services\GoogleAuthentication::enabled())
        <form method="POST" action="{{ route('google.login') }}">
            @csrf
            <button type="submit">Sign in with Google</button>
        </form>
        <p>Already have an account? Sign in with your password first to connect Google in Account sign-in.</p>
    @endif
    <p><a href="{{ route('password.request') }}">Forgot your password?</a></p>
    <form wire:submit="login">
        <label for="email">Email</label>
        <input id="email" type="email" wire:model="email" autocomplete="username" required>
        @error('email') <p role="alert">{{ $message }}</p> @enderror
        <label for="password">Password</label>
        <input id="password" type="password" wire:model="password" autocomplete="current-password" required>
        @error('password') <p role="alert">{{ $message }}</p> @enderror
        <button type="submit" wire:loading.attr="disabled">Sign in</button>
    </form>
</section>
