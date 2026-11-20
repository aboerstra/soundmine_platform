@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Product list pages">
        <p class="muted text-sm">Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }} licenses</p>
        <div class="flex items-center gap-3">
            <button class="button button-secondary" type="button" wire:click="previousPage('page')" wire:loading.attr="disabled" @disabled($paginator->onFirstPage()) aria-label="Previous page">← Previous</button>
            <span class="mono muted">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
            <button class="button button-secondary" type="button" wire:click="nextPage('page')" wire:loading.attr="disabled" @disabled(! $paginator->hasMorePages()) aria-label="Next page">Next →</button>
        </div>
    </nav>
@endif
