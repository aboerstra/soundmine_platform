<?php

namespace App\Http\Middleware;

use App\Domain\Activations\Exceptions\ActivationException;
use App\Domain\Activations\Models\InstallationToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateInstallation
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();
        if (! is_string($plainTextToken) || ! preg_match('/^sm_install_[A-Za-z0-9]{64}$/', $plainTextToken)) {
            throw new ActivationException('invalid_installation_token', 'Provide a valid installation bearer token.', 401);
        }
        $token = InstallationToken::with('activation')->where('token_hash', hash('sha256', $plainTextToken))->first();
        if (! $token) {
            throw new ActivationException('invalid_installation_token', 'The installation token is invalid or expired.', 401);
        }
        // Scope, expiry and revocation are rechecked inside the action's license transaction.
        $request->attributes->set('installation_credential', $token);

        return $next($request);
    }
}
