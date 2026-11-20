<section class="auth-stage">
    <x-portal.auth-aside />
    <div class="card auth-card space-y-6">
        <div class="space-y-3"><p class="eyebrow">Account recovery</p><h1 class="text-3xl">Choose a new password</h1><p class="muted text-sm">Use at least 12 characters. Password managers and pasting are welcome.</p></div>
        <x-portal.errors />
        <form wire:submit="resetPassword" class="space-y-5">
            <x-portal.field name="email" label="Email address" type="email" wire:model="email" autocomplete="username" required />
            <x-portal.field name="password" label="New password" type="password" wire:model="password" autocomplete="new-password" minlength="12" hint="At least 12 characters." required />
            <x-portal.field name="password_confirmation" label="Confirm new password" type="password" wire:model="password_confirmation" autocomplete="new-password" required />
            <x-portal.submit action="resetPassword" label="Reset password" busy="Updating password…" class="w-full" />
        </form>
        <a class="text-link inline-block text-sm" href="{{ route('login') }}">Back to sign in</a>
    </div>
</section>
