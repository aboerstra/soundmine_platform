<section class="auth-stage">
    <x-portal.auth-aside />
    <div class="card auth-card space-y-6">
        <div class="space-y-3"><p class="eyebrow">Welcome back</p><h1 class="text-3xl">Sign in to TheSoundMine</h1><p class="muted text-sm">Access your products, licenses and account.</p></div>
        @if (session('status')) <p class="notice notice-success" role="status">{{ session('status') }}</p> @endif
        <x-portal.errors />
        <form wire:submit="login" class="space-y-5">
            <x-portal.field name="email" label="Email address" type="email" wire:model="email" autocomplete="username" required />
            <x-portal.field name="password" label="Password" type="password" wire:model="password" autocomplete="current-password" required />
            <div class="text-right"><a class="text-link text-sm" href="{{ route('password.request') }}">Forgot your password?</a></div>
            <x-portal.submit action="login" label="Sign in" busy="Signing in…" class="w-full" />
        </form>
        @if (\App\Domain\Customers\Services\GoogleAuthentication::enabled())
            <div class="divider">or</div>
            <form method="POST" action="{{ route('google.login') }}">@csrf<button class="button button-secondary w-full" type="submit">Sign in with Google</button></form>
            <p class="field-help">Already have an account? Sign in with your password first to connect Google in Account sign-in.</p>
        @endif
        <p class="border-t border-[var(--tsm-rock)] pt-5 text-sm muted">New to TheSoundMine? Open the invitation email sent to you to create your account.</p>
    </div>
</section>
