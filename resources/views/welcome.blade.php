<x-layouts.portal title="Your creative tools">
    <section class="auth-stage">
        <div class="auth-aside space-y-6"><img src="{{ asset('brand/thesoundmine-mark-on-dark.svg') }}" alt="" width="96" height="96"><p class="eyebrow">TheSoundMine</p><h2 class="text-4xl md:text-5xl">Your tools.<br>All in one place.</h2><p class="muted text-lg">Your account is the home for your products and licenses.</p></div>
        <div class="card auth-card space-y-6">
            <p class="eyebrow">Customer portal</p><h1 class="text-3xl">Ready when you are.</h1><p class="muted">Sign in to find your products, view license details and authorize an installation.</p>
            <a class="button button-primary w-full" href="{{ auth()->check() ? route('portal') : route('login') }}">{{ auth()->check() ? 'Open your products' : 'Sign in to your account' }}</a>
            <p class="field-help">Creating an account? Follow the invitation email sent to you. If you can’t find it, ask the person who invited you for a new link.</p>
        </div>
    </section>
</x-layouts.portal>
