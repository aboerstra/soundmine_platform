<section class="space-y-8">
    <nav aria-label="Breadcrumb"><a class="text-link text-sm" href="{{ route('portal') }}">← Your products</a></nav>
    <div class="flex flex-wrap items-start justify-between gap-5">
        <div class="page-intro"><p class="eyebrow">Product license</p><div class="flex items-center gap-4"><x-portal.product-icon :product="$license->product" /><h1>{{ $license->product->name }}</h1></div></div>
        <x-portal.license-status :license="$license" />
    </div>
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)] items-start">
        <div class="space-y-6">
            <section class="card space-y-6" aria-labelledby="license-heading"><h2 id="license-heading">License details</h2>
                <dl class="details-grid">
                    <div><dt>Installations</dt><dd><span class="text-2xl font-bold">{{ $license->active_activations_count }}</span><span class="muted"> / {{ $license->max_activations }} in use</span></dd></div>
                    <div><dt>Valid until</dt><dd>{{ $license->expires_at ? $license->expires_at->format('d M Y, H:i').' UTC' : 'No fixed expiry' }}</dd></div>
                    <div><dt>Starts</dt><dd>{{ $license->starts_at ? $license->starts_at->format('d M Y, H:i').' UTC' : 'Not set' }}</dd></div>
                    <div><dt>License reference</dt><dd class="mono">{{ $license->id }}</dd></div>
                </dl>
            </section>
            <section class="card space-y-5" aria-labelledby="rights-heading"><h2 id="rights-heading">Included rights</h2>
                <dl class="space-y-4">
                    @forelse ($license->entitlements as $entitlement)
                        <div wire:key="{{ $entitlement->id }}" class="space-y-1"><dt class="muted text-sm break-words">{{ $entitlement->key }}</dt><dd class="mono">{{ is_string($entitlement->value) ? $entitlement->value : json_encode($entitlement->value) }}</dd></div>
                    @empty <div><dt class="sr-only">Additional rights</dt><dd class="muted text-sm">No additional entitlements recorded.</dd></div>
                    @endforelse
                </dl>
            </section>
        </div>
        <section class="card space-y-6" aria-labelledby="authorize-heading">
            <div class="space-y-3"><p class="eyebrow">Installation setup</p><h2 id="authorize-heading">Authorize an installation</h2><p class="muted text-sm">Create a temporary code for {{ $license->product->name }} on one macOS or Windows installation.</p></div>
            <x-portal.errors />
            @if ($unavailableReason)
                <div class="notice" role="status"><p class="font-bold">Authorization unavailable</p><p class="mt-2">{{ $unavailableReason }}</p><p class="field-help mt-2">You can still review your license details. If this is unexpected, contact the person who provided your license.</p></div>
            @else
                <ol class="grid gap-2 text-sm muted"><li><span class="step-number">01</span> Copy your installation fingerprint.</li><li><span class="step-number">02</span> Choose your platform and generate a code.</li><li><span class="step-number">03</span> Paste the code into your application.</li></ol>
                <form wire:submit="issueAuthorization" class="space-y-5">
                    <x-portal.field name="machine_hash" label="Installation fingerprint" wire:model.live.debounce.300ms="machineHash" minlength="64" maxlength="64" autocomplete="off" autocapitalize="none" spellcheck="false" hint="Paste the 64-character fingerprint supplied for this installation. It uses lowercase letters a–f and numbers 0–9." required />
                    <div class="field"><label for="platform">Platform</label><select class="input" id="platform" wire:model.live="platform" aria-invalid="{{ $errors->has('platform') ? 'true' : 'false' }}" @error('platform') aria-describedby="platform-error" @enderror><option value="macos">macOS</option><option value="windows">Windows</option></select>@error('platform')<p class="field-error" id="platform-error">{{ $message }}</p>@enderror</div>
                    <x-portal.submit action="issueAuthorization" label="Generate authorization code" busy="Generating code…" class="w-full" />
                </form>
                @if ($authorizationCode)
                    <div class="notice notice-success space-y-3" wire:key="{{ $authorizationExpiresAt }}" role="status" tabindex="-1" x-data="{copied:false, copyError:false}" x-init="$nextTick(() => $el.focus())">
                        <h3>Your authorization code is ready</h3><p class="text-sm">Copy it into your application before {{ \Carbon\CarbonImmutable::parse($authorizationExpiresAt)->format('d M Y, H:i') }} UTC.</p>
                        <label class="sr-only" for="authorization-code">Authorization code</label><textarea class="input mono" id="authorization-code" x-ref="code" rows="3" readonly spellcheck="false">{{ $authorizationCode }}</textarea>
                        <button class="button button-secondary" type="button" x-on:click="if (navigator.clipboard) { navigator.clipboard.writeText($refs.code.value).then(() => { copied = true; copyError = false }).catch(() => { $refs.code.select(); copyError = true }) } else { $refs.code.select(); copyError = true }"><span x-text="copied ? 'Copied' : 'Copy code'">Copy code</span></button>
                        <p class="field-help" x-cloak x-show="copied" aria-live="polite">Code copied. Return to your application to finish authorization.</p><p class="field-help" x-cloak x-show="copyError" role="alert">Copy was blocked by your browser. The code is selected; use your device’s Copy command.</p>
                        <p class="field-help">This code lasts 10 minutes and is bound to the fingerprint and platform above. Changing either clears this preview. Generating a code does not reserve an installation.</p>
                    </div>
                @endif
                <details class="border-t border-[var(--tsm-rock)] pt-4 text-sm"><summary class="min-h-11 py-2 font-bold">Where do I find the fingerprint?</summary><p class="muted mt-2">Use the fingerprint supplied by your application or its setup instructions. If the application does not offer one yet, this preview is not ready for that installation.</p></details>
            @endif
            <p class="field-help border-t border-[var(--tsm-rock)] pt-4">Online authorization is in preview. Offline authorization is not available yet.</p>
        </section>
    </div>
</section>
