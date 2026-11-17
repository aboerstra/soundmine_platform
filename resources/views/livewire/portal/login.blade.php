<section>
    <h1>Sign in to Soundmine</h1>
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
