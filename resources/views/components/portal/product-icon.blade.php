@props(['product'])
@php
    $icon = match ($product->slug) {
        'rhythm-conductor', 'pattern-conductor' => 'products/thesoundmine-pattern-conductor-mark-on-dark.svg',
        'harmonic-conductor' => 'products/thesoundmine-harmonic-conductor-mark-on-dark.svg',
        'studio' => 'products/thesoundmine-studio-mark-on-dark.svg',
        default => 'thesoundmine-mark-on-dark.svg',
    };
@endphp
<img class="product-icon" src="{{ asset('brand/'.$icon) }}" width="96" height="96" alt="">
