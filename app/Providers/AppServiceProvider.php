<?php

namespace App\Providers;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Policies\ProductPolicy;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerInvitation;
use App\Domain\Customers\Policies\CustomerInvitationPolicy;
use App\Domain\Customers\Policies\CustomerPolicy;
use App\Domain\Licensing\Models\License;
use App\Domain\Licensing\Policies\LicensePolicy;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $throttleResponse = fn (Request $request, array $headers): JsonResponse => response()->json([
            'error' => ['code' => 'rate_limited', 'message' => 'Too many activation requests.'],
        ], 429, $headers + ['Cache-Control' => 'no-store, private']);
        RateLimiter::for('activation-exchange', fn (Request $request): Limit => Limit::perMinute(30)
            ->by('exchange:'.$request->ip())->response($throttleResponse));
        RateLimiter::for('installation-api', fn (Request $request): array => [
            Limit::perMinute(300)->by('installation-ip:'.$request->ip())->response($throttleResponse),
            Limit::perMinute(120)->by('installation-token:'.hash('sha256', $request->bearerToken() ?? ''))->response($throttleResponse),
        ]);
        Livewire::addPersistentMiddleware([AuthenticateSession::class, EnsureEmailIsVerified::class]);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(CustomerInvitation::class, CustomerInvitationPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(License::class, LicensePolicy::class);
    }
}
