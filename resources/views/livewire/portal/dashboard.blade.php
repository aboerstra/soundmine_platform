<section>
    <h1>Your Soundmine products</h1>
    <p>Online activation authorization is available from each license detail page. Signed certificates and offline authorization are not available yet.</p>
    <ul>
        @forelse ($licenses as $license)
            <li wire:key="{{ $license->id }}"><a href="{{ route('portal.licenses.show', $license) }}">{{ $license->product->name }}</a> — {{ $license->status->value }}</li>
        @empty
            <li>No licenses are linked to your account.</li>
        @endforelse
    </ul>
    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form>
</section>
