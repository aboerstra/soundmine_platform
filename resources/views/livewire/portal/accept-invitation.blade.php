<section>
    <h1>Create your TheSoundMine account</h1>
    <p>Your invitation fixes your account name and email address. Choose a password of at least 12 characters.</p>
    @if (\App\Domain\Customers\Services\GoogleAuthentication::enabled())
        <form method="POST" action="{{ route('google.invitation', ['invitation' => $invitationId, 'token' => $token]) }}">
            @csrf
            <button type="submit">Accept invitation with Google</button>
        </form>
        <p>Choose the verified Google email matching your invitation. You can also create an account with a password below.</p>
    @endif
    <form wire:submit="accept">
        <label for="password">Password</label>
        <input id="password" type="password" wire:model="password" autocomplete="new-password" required>
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required>
        @error('password') <p role="alert">{{ $message }}</p> @enderror
        @error('token') <p role="alert">{{ $message }}</p> @enderror
        <button type="submit" wire:loading.attr="disabled">Create account</button>
    </form>
</section>
