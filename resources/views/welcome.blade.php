<x-layouts.portal title="Your creative tools">
    <section class="auth-stage">
        <div class="auth-aside space-y-6"><img src="{{ asset('brand/thesoundmine-mark-on-dark.svg') }}" alt="" width="96" height="96"><p class="eyebrow">TheSoundMine</p><h2 class="text-4xl md:text-5xl">Your tools.<br>All in one place.</h2><p class="muted text-lg">Your account is the home for your products and licenses.</p></div>
        <div class="card auth-card space-y-6">
            <p class="eyebrow">Customer portal</p><h1 class="text-3xl">Ready when you are.</h1><p class="muted">Sign in to find your products, view license details and authorize an installation.</p>
            <a class="button button-primary w-full" href="{{ auth()->check() ? route('portal') : route('login') }}">{{ auth()->check() ? 'Open your products' : 'Sign in to your account' }}</a>
            <p class="field-help">Creating an account? Follow the invitation email sent to you. If you can’t find it, ask the person who invited you for a new link.</p>
        </div>
    </section>
    <section class="mt-12 space-y-5" aria-labelledby="products-heading">
        <h2 id="products-heading" class="text-3xl">Hardware-first music workflows</h2>
        <p class="muted">TheSoundMine helps musicians coordinate patterns, harmony, and signal paths across hardware and software.</p>
        <div class="grid gap-4 md:grid-cols-3">
            <section class="card space-y-4">
                <h3><img class="h-10 w-full object-contain object-left" src="{{ asset('brand/products/thesoundmine-pattern-conductor-lockup-on-dark.svg') }}" alt="TheSoundMine Pattern Conductor"></h3>
                <p class="muted">Cue pattern changes across hardware sequencers on a shared musical boundary.</p>
            </section>
            <section class="card space-y-4">
                <h3><img class="h-10 w-full object-contain object-left" src="{{ asset('brand/products/thesoundmine-harmonic-conductor-lockup-on-dark.svg') }}" alt="TheSoundMine Harmonic Conductor"></h3>
                <p class="muted">Use live chords to guide pitch changes and voice leading on controllable signal paths.</p>
            </section>
            <section class="card space-y-4">
                <h3><img class="h-10 w-full object-contain object-left" src="{{ asset('brand/products/thesoundmine-studio-lockup-on-dark.svg') }}" alt="TheSoundMine Studio"></h3>
                <p class="muted">Model your gear and its connections to plan workflows around the studio you have.</p>
            </section>
        </div>
        <p class="muted">The customer portal and online activation are in preview.</p>
    </section>
</x-layouts.portal>
