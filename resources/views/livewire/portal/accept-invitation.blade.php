<section>
    <h1>Create your Soundmine account</h1>
    <p>Your invitation fixes your account name and email address. Choose a password of at least 12 characters.</p>
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
