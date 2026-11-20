<?php

namespace App\Livewire\Portal;

use App\Domain\Licensing\Models\License;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class Dashboard extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $customer = auth()->user()->customer;
        $query = $customer ? $customer->licenses() : License::whereRaw('1 = 0');
        $search = mb_substr(trim($this->search), 0, 100);
        if ($search !== '') {
            $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->whereHas('product', fn (Builder $products) => $products->where('name', 'ilike', $pattern));
        }

        return view('livewire.portal.dashboard', [
            'licenses' => $query->with('product')->orderByDesc('created_at')->orderByDesc('id')->paginate(12),
        ])->layout('components.layouts.portal', ['title' => 'Your products']);
    }
}
