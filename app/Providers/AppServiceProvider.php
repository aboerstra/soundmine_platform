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
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Gate;
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
        Livewire::addPersistentMiddleware([AuthenticateSession::class, EnsureEmailIsVerified::class]);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(CustomerInvitation::class, CustomerInvitationPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(License::class, LicensePolicy::class);
    }
}
