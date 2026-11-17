<?php

namespace App\Livewire\Portal;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
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
