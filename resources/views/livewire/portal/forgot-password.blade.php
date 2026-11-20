<section class="auth-stage">
    <x-portal.auth-aside />
    <div class="card auth-card space-y-6">
        <div class="space-y-3"><p class="eyebrow">Account recovery</p><h1 class="text-3xl">Reset your password</h1><p class="muted text-sm">Enter your account email. We’ll send a link so you can choose a new password.</p></div>
        <x-portal.errors />
        @if ($sent)
            <div class="notice notice-success space-y-2" role="status"><p class="font-bold">Check your inbox</p><p>If an account matches that email, a password reset link has been sent.</p><p class="field-help">Check your spam folder too. The link is valid for 60 minutes.</p></div>
        @endif
        <form wire:submit="sendLink" class="space-y-5">
            <x-portal.field name="email" label="Email address" type="email" wire:model="email" autocomplete="username" required />
            <x-portal.submit action="sendLink" label="Send reset link" busy="Sending link…" class="w-full" />
        </form>
        <a class="text-link inline-block text-sm" href="{{ route('login') }}">Back to sign in</a>
    </div>
</section>
