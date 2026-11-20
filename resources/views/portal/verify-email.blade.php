<x-layouts.portal title="Verify your email">
    <section class="auth-stage">
        <x-portal.auth-aside />
        <div class="card auth-card space-y-6">
            <div class="space-y-3"><p class="eyebrow">One more step</p><h1 class="text-3xl">Verify your email</h1><p class="muted">Follow the link sent to your account email before viewing your licenses.</p></div>
            <p class="notice break-all">{{ auth()->user()->email }}</p>
            @if (session('status')) <p class="notice notice-success" role="status">{{ session('status') }}</p> @endif
            <p class="field-help">Can’t find it? Check your spam folder, then request another email.</p>
            <form method="POST" action="{{ route('verification.send') }}">@csrf<button class="button button-primary w-full" type="submit">Resend verification email</button></form>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-link text-sm" type="submit">Use a different account</button></form>
        </div>
    </section>
</x-layouts.portal>
