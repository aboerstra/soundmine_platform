<x-layouts.portal title="This page isn’t available">
    <section class="card max-w-2xl mx-auto space-y-6"><p class="eyebrow">404</p><h1>This page isn’t available</h1><p class="muted">The link may have expired or you may not have access. Check the address, or return to your account. For an invitation, ask the sender for a fresh link.</p><a class="button button-primary" href="{{ auth()->check() ? route('portal') : route('login') }}">Return to your account</a></section>
</x-layouts.portal>
