@props(['title' => null])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="color-scheme" content="dark">
    <title>{{ $title ?? 'Customer portal' }} · TheSoundMine</title>
    <link rel="icon" href="{{ asset('brand/favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('brand/fonts.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    <div class="portal-shell">
        <a class="skip-link" href="#main-content">Skip to content</a>
        <header class="site-header">
            <div class="shell-width flex flex-wrap items-center justify-between gap-x-8 gap-y-2 py-3">
                <a class="brand-link" href="{{ auth()->check() ? route('portal') : url('/') }}" aria-label="TheSoundMine home">
                    <img src="{{ asset('brand/thesoundmine-lockup-on-dark.svg') }}" width="496" height="96" alt="TheSoundMine">
                </a>
                @auth
                    <nav aria-label="Main navigation" class="flex flex-wrap items-center gap-1">
                        <a class="nav-link" href="{{ route('portal') }}" @if(request()->routeIs('portal', 'portal.licenses.*')) aria-current="page" @endif>Your products</a>
                        @if (! auth()->user()->is_admin)
                            <a class="nav-link" href="{{ route('portal.account') }}" @if(request()->routeIs('portal.account')) aria-current="page" @endif>Account sign-in</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-link" type="submit">Sign out</button></form>
                    </nav>
                @else
                    <span class="eyebrow">Customer portal</span>
                @endauth
            </div>
        </header>
        <main id="main-content" tabindex="-1" class="shell-width py-10 md:py-14">{{ $slot }}</main>
        <footer class="site-footer">
            <div class="shell-width flex flex-wrap justify-between gap-3">
                <p>TheSoundMine <span aria-hidden="true">/</span> Customer portal.</p>
                <p>Products, licenses and account access.</p>
            </div>
        </footer>
    </div>
    @livewireScripts
</body>
</html>
