<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TheSoundMine</title>
    <x-brand.assets />
</head>
<body class="tsm-page">
    <x-brand.header />

    <main class="tsm-shell tsm-home">
        <section class="tsm-hero">
            <h1>Hardware-first music workflows</h1>
            <p>TheSoundMine helps musicians coordinate patterns, harmony, and signal paths across hardware and software.</p>
            <a class="tsm-action" href="{{ route('portal') }}">Customer portal</a>
        </section>

        <div class="tsm-product-grid" aria-label="TheSoundMine products">
            <section class="tsm-product tsm-product--pattern">
                <h2><img src="{{ asset('brand/products/thesoundmine-pattern-conductor-lockup-on-dark.svg') }}" alt="TheSoundMine Pattern Conductor"></h2>
                <p>Cue pattern changes across hardware sequencers on a shared musical boundary.</p>
            </section>
            <section class="tsm-product tsm-product--harmonic">
                <h2><img src="{{ asset('brand/products/thesoundmine-harmonic-conductor-lockup-on-dark.svg') }}" alt="TheSoundMine Harmonic Conductor"></h2>
                <p>Use live chords to guide pitch changes and voice leading on controllable signal paths.</p>
            </section>
            <section class="tsm-product tsm-product--studio">
                <h2><img src="{{ asset('brand/products/thesoundmine-studio-lockup-on-dark.svg') }}" alt="TheSoundMine Studio"></h2>
                <p>Model your gear and its connections to plan workflows around the studio you have.</p>
            </section>
        </div>

        <p class="tsm-availability">The customer portal and online activation are in preview.</p>
    </main>
</body>
</html>
