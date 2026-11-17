<section>
    <h1>Your Soundmine products</h1>
    <p>License activation and offline authorization are not available yet.</p>
    <ul>
        @forelse ($licenses as $license)
            <li wire:key="{{ $license->id }}">{{ $license->product->name }} — {{ $license->status->value }}</li>
        @empty
            <li>No licenses are linked to your account.</li>
        @endforelse
    </ul>
    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form>
</section>
