@props(['action', 'label', 'busy'])
<button type="submit" wire:loading.attr="disabled" wire:target="{{ $action }}" {{ $attributes->class(['button button-primary']) }}>
    <span wire:loading.remove wire:target="{{ $action }}">{{ $label }}</span>
    <span wire:loading wire:target="{{ $action }}" role="status">{{ $busy }}</span>
</button>
