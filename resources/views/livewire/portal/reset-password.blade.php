<section>
    <h1>Choose a new password</h1>
    <form wire:submit="resetPassword">
        <label for="email">Email</label>
        <input id="email" type="email" wire:model="email" autocomplete="username" required>
        <label for="password">New password (at least 12 characters)</label>
        <input id="password" type="password" wire:model="password" autocomplete="new-password" required>
        <label for="password_confirmation">Confirm password</label>
        <input id="password_confirmation" type="password" wire:model="password_confirmation" autocomplete="new-password" required>
        @error('email') <p role="alert">{{ $message }}</p> @enderror
        @error('password') <p role="alert">{{ $message }}</p> @enderror
        <button type="submit" wire:loading.attr="disabled">Reset password</button>
    </form>
</section>
