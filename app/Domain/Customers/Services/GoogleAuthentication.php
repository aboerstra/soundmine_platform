<?php

namespace App\Domain\Customers\Services;

use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Socialite;

class GoogleAuthentication
{
    public static function enabled(): bool
    {
        return config('services.google.enabled')
            && filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public function provider(): Provider
    {
        abort_unless(self::enabled(), 404);

        return Socialite::driver('google')->setRequest(request());
    }
}
