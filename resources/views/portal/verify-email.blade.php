<x-layouts.portal>
    <h1>Verify your email</h1>
    <p>Follow the verification link sent to your account email before viewing your licenses.</p>
    @if (session('status')) <p role="status">{{ session('status') }}</p> @endif
    <form method="POST" action="{{ route('verification.send') }}">@csrf<button type="submit">Resend verification email</button></form>
    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Sign out</button></form>
</x-layouts.portal>
