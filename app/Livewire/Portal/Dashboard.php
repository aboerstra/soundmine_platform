<?php

namespace App\Livewire\Portal;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(): View
    {
        $customer = auth()->user()->customer;

        return view('livewire.portal.dashboard', [
            'licenses' => $customer?->licenses()->with('product')->latest()->get() ?? collect(),
        ])->layout('components.layouts.portal');
    }
}
