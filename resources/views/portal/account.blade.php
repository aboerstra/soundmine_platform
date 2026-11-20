<x-layouts.portal>
    <section>
        <a href="{{ route('portal') }}">Your products</a>
        <h1>Account sign-in</h1>
        @if (session('status')) <p role="status">{{ session('status') }}</p> @endif
        @error('google') <p role="alert">{{ $message }}</p> @enderror
        @error('password') <p role="alert">{{ $message }}</p> @enderror
        @if ($googleLinked)
            <p>Google sign-in is connected to your Soundmine account.</p>
            <form method="POST" action="{{ route('google.unlink') }}">
                @csrf
                @method('DELETE')
                <label for="password">Confirm your Soundmine password to disconnect Google</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                <button type="submit">Disconnect Google</button>
            </form>
        @elseif ($googleEnabled)
            <p>Connect the Google account whose email matches {{ auth()->user()->email }}. Your products and licenses stay with this Soundmine account.</p>
            <form method="POST" action="{{ route('google.link') }}">
                @csrf
                <label for="password">Confirm your Soundmine password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                <button type="submit">Connect Google</button>
            </form>
        @else
            <p>Google sign-in is not currently available.</p>
        @endif
        <p>If you created your account with Google, sign out and use <a href="{{ route('password.request') }}">password recovery</a> to set a Soundmine password before disconnecting it.</p>
    </section>
</x-layouts.portal>
