<section class="auth-stage">
    <x-portal.auth-aside />
    <div class="card auth-card space-y-6">
        <div class="space-y-3"><p class="eyebrow">You’re invited</p><h1 class="text-3xl">Create your TheSoundMine account</h1><p class="muted text-sm">Your invitation sets your account name and email address.</p></div>
        <div class="notice"><span class="muted text-xs block">Invited email</span><span class="break-all">{{ $invitedEmail }}</span></div>
        <x-portal.errors />
        @if (\App\Domain\Customers\Services\GoogleAuthentication::enabled())
            <form method="POST" action="{{ route('google.invitation', ['invitation' => $invitationId, 'token' => $token]) }}">@csrf<button class="button button-secondary w-full" type="submit">Accept invitation with Google</button></form>
            <p class="field-help">Choose the verified Google email matching your invitation. You can also create an account with a password below.</p>
            <div class="divider">or use a password</div>
        @endif
        <form wire:submit="accept" class="space-y-5">
            <x-portal.field name="password" label="Choose a password" type="password" wire:model="password" autocomplete="new-password" minlength="12" hint="At least 12 characters. You can use a password manager." required />
            <x-portal.field name="password_confirmation" label="Confirm password" type="password" wire:model="password_confirmation" autocomplete="new-password" required />
            <x-portal.submit action="accept" label="Create account" busy="Creating account…" class="w-full" />
        </form>
        <p class="field-help">Already accepted your invitation? <a class="text-link" href="{{ route('login') }}">Sign in</a>.</p>
    </div>
</section>
