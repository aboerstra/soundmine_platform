<section class="space-y-8">
    <div class="page-intro"><p class="eyebrow">Your creative toolkit</p><h1>Your products</h1><p class="muted">All your TheSoundMine licenses, together. Choose a product to view its license and installation options.</p></div>
    <div class="flex flex-wrap items-end justify-between gap-5 border-b border-[var(--tsm-rock)] pb-6">
        <form wire:submit="$refresh" role="search" class="w-full max-w-md">
            <x-portal.field name="search" label="Find a product" type="search" wire:model.live.debounce.300ms="search" maxlength="100" placeholder="Search by product name" autocomplete="off" />
        </form>
        <p class="muted text-sm" role="status" aria-live="polite">{{ $licenses->total() }} {{ Str::plural('license', $licenses->total()) }}{{ $search !== '' ? ' found' : '' }} <span wire:loading wire:target="search" class="ml-2">Searching…</span></p>
    </div>
    <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($licenses as $license)
            <article wire:key="{{ $license->id }}" class="card product-card">
                <div class="flex items-start justify-between gap-4"><x-portal.product-icon :product="$license->product" /><x-portal.license-status :license="$license" /></div>
                <div class="space-y-2"><h2 class="text-xl">{{ $license->product->name }}</h2><p class="mono muted">License · {{ Str::upper(Str::substr($license->id, -8)) }}</p></div>
                <dl class="details-grid text-sm"><div><dt>Device allowance</dt><dd>{{ $license->max_activations }} {{ Str::plural('installation', $license->max_activations) }}</dd></div><div><dt>Valid until</dt><dd>{{ $license->expires_at?->format('d M Y') ?? 'No fixed expiry' }}</dd></div></dl>
                <a class="button button-secondary w-full" href="{{ route('portal.licenses.show', $license) }}" aria-label="View {{ $license->product->name }} license {{ Str::upper(Str::substr($license->id, -8)) }}">View license <span aria-hidden="true">→</span></a>
            </article>
        @empty
            <div class="card empty-state md:col-span-2 xl:col-span-3 space-y-4">
                @if ($search !== '')
                    <h2>No matching products</h2><p class="muted">Try a different product name, or clear your search to see all your licenses.</p><button class="button button-secondary" type="button" wire:click="$set('search', '')">Clear search</button>
                @else
                    <h2>Your toolkit starts here</h2><p class="muted">No licenses are linked to your account yet.</p><p class="field-help">If you expected a product here, check that you signed in with the email used for your invitation.</p>
                @endif
            </div>
        @endforelse
    </div>
    {{ $licenses->links('components.portal.pagination') }}
    <aside class="notice"><p class="font-bold">Setting up an installation?</p><p class="muted mt-1">Open a license to find its authorization options. Online authorization is in preview; offline authorization is not available yet.</p></aside>
</section>
