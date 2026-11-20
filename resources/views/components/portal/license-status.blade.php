@props(['license'])
@php
    $state = $license->status->value;
    $label = ucfirst($state);
    if ($state === 'active' && $license->expires_at?->lessThanOrEqualTo(now())) { $state = 'expired'; $label = 'Expired'; }
    elseif ($state === 'active' && $license->starts_at?->isFuture()) { $state = 'pending'; $label = 'Not started'; }
@endphp
<span class="status status-{{ $state }}">{{ $label }}</span>
