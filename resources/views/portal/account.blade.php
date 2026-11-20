<x-layouts.portal title="Account sign-in">
    <section class="space-y-8 max-w-3xl">
        <div class="page-intro"><p class="eyebrow">Your account</p><h1>Account sign-in</h1><p class="muted">Manage how you sign in. Your products and licenses stay with this account.</p></div>
        @if (session('status')) <p class="notice notice-success" role="status">{{ session('status') }}</p> @endif
        <x-portal.errors />
        <div class="card space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4"><h2>Google sign-in</h2><span class="status {{ $googleLinked ? 'status-active' : '' }}">{{ $googleLinked ? 'Connected' : 'Not connected' }}</span></div>
            <p class="mono muted break-all">{{ auth()->user()->email }}</p>
            @if ($googleLinked)
                <p>Google sign-in is connected to your TheSoundMine account. You can disconnect it after confirming your password.</p>
                <form method="POST" action="{{ route('google.unlink') }}" class="space-y-5">
                    @csrf @method('DELETE')
                    <x-portal.field name="password" label="Confirm your TheSoundMine password" type="password" autocomplete="current-password" hint="Your password will still work after you disconnect Google." required />
                    <button class="button button-secondary" type="submit">Disconnect Google</button>
                </form>
            @elseif ($googleEnabled)
                <p>Connect the Google account whose verified email matches the address above.</p>
                <form method="POST" action="{{ route('google.link') }}" class="space-y-5">
                    @csrf
                    <x-portal.field name="password" label="Confirm your TheSoundMine password" type="password" autocomplete="current-password" hint="We’ll ask you to choose your Google account next." required />
                    <button class="button button-primary" type="submit">Connect Google</button>
                </form>
            @else
                <p class="notice">Google sign-in is not currently available. You can continue using your password.</p>
            @endif
        </div>
        <div class="card space-y-4">
            <h2>Password help</h2>
            <p class="muted">If you created your account with Google, set a TheSoundMine password before disconnecting it. Sign out, then choose “Forgot your password?” on the sign-in page.</p>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="button button-secondary" type="submit">Sign out to reset password</button></form>
        </div>
    </section>
</x-layouts.portal>
