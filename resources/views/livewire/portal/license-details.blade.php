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
    <h2>Authorize an installation</h2>
    <p>Paste the installation fingerprint from your Soundmine application. This authorizes only {{ $license->product->name }} under this license.</p>
    <form wire:submit="issueAuthorization">
        <label for="machineHash">Installation fingerprint (64 lowercase hexadecimal characters)</label>
        <input id="machineHash" wire:model="machineHash" type="text" minlength="64" maxlength="64" required autocomplete="off">
        @error('machine_hash') <p role="alert">{{ $message }}</p> @enderror
        <label for="platform">Platform</label>
        <select id="platform" wire:model="platform"><option value="macos">macOS</option><option value="windows">Windows</option></select>
        @error('platform') <p role="alert">{{ $message }}</p> @enderror
        @error('authorization') <p role="alert">{{ $message }}</p> @enderror
        <button type="submit" wire:loading.attr="disabled">Generate authorization code</button>
    </form>
    @if ($authorizationCode)
        <p>Copy this code into your application. It expires at {{ $authorizationExpiresAt }} and authorizes one exchange.</p>
        <code>{{ $authorizationCode }}</code>
    @endif
    <p>Online activation is a preview API. Signed certificates and offline authorization are not available yet.</p>
</section>
