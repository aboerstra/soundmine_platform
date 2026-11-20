<x-layouts.portal title="Your session has expired">
    <section class="card max-w-2xl mx-auto space-y-6"><p class="eyebrow">419</p><h1>Your session has expired</h1><p class="muted">For your security, this page needs a fresh session. Sign in again and retry your last action.</p><a class="button button-primary" href="{{ auth()->check() ? route('portal') : route('login') }}">Return to your account</a></section>
</x-layouts.portal>
