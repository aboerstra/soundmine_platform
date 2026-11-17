<?php

namespace App\Livewire\Portal;

use App\Domain\Activations\Actions\IssueActivationAuthorization;
use App\Domain\Activations\Exceptions\ActivationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class LicenseDetails extends Component
{
    #[Locked]
    public string $licenseId;

    public function mount(string $license): void
    {
        $this->licenseId = $license;
    }

    public string $machineHash = '';

    public string $platform = 'macos';

    #[Locked]
    public ?string $authorizationCode = null;

    #[Locked]
    public ?string $authorizationExpiresAt = null;

    public function issueAuthorization(IssueActivationAuthorization $issue): void
    {
        $this->authorizationCode = null;
        $customer = auth()->user()->customer;
        abort_unless($customer, 404);
        $license = $customer->licenses()->findOrFail($this->licenseId);
        $key = 'activation-authorize:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 20)) {
            throw ValidationException::withMessages(['authorization' => 'Too many requests. Please try again later.']);
        }
        RateLimiter::hit($key, 60);
        try {
            $result = $issue->handle(auth()->user(), $license, ['machine_hash' => $this->machineHash, 'platform' => $this->platform]);
        } catch (ActivationException $exception) {
            throw ValidationException::withMessages(['authorization' => $exception->getMessage()]);
        }
        $this->authorizationCode = $result['code'];
        $this->authorizationExpiresAt = $result['expires_at'];
    }

    public function render(): View
    {
        // Resolve from the current customer on every render, including Livewire updates.
        $customer = auth()->user()->customer;
        abort_unless($customer, 404);
        $license = $customer->licenses()->with(['product', 'entitlements'])->findOrFail($this->licenseId);

        Gate::authorize('view', $license);

        return view('livewire.portal.license-details', ['license' => $license])->layout('components.layouts.portal');
    }
}
