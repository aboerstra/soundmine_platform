<section>
    <a href="{{ route('portal') }}">Your products</a>
    <h1>{{ $license->product->name }}</h1>
    <dl>
        <dt>License reference</dt><dd>{{ $license->id }}</dd>
        <dt>Status</dt><dd>{{ $license->status->value }}</dd>
        <dt>Starts</dt><dd>{{ $license->starts_at?->toDateTimeString() ?? 'Not set' }} UTC</dd>
        <dt>Expires</dt><dd>{{ $license->expires_at?->toDateTimeString() ?? 'No fixed expiry' }}</dd>
        <dt>Device allowance</dt><dd>{{ $license->max_activations }}</dd>
    </dl>
    <h2>Entitlements</h2>
    <ul>
        @forelse ($license->entitlements as $entitlement)
            <li wire:key="{{ $entitlement->id }}">{{ $entitlement->key }}: {{ is_string($entitlement->value) ? $entitlement->value : json_encode($entitlement->value) }}</li>
        @empty <li>No entitlements recorded.</li>
        @endforelse
    </ul>
    <p>This record does not activate the plugin. Activation and offline authorization are not available yet.</p>
</section>
