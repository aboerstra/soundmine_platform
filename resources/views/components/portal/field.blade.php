@props(['name', 'label', 'hint' => null, 'type' => 'text'])
<div class="field">
    <label for="{{ $name }}">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
        @if($hint || $errors->has($name)) aria-describedby="{{ $hint ? $name.'-help' : '' }} {{ $errors->has($name) ? $name.'-error' : '' }}" @endif
        {{ $attributes->class(['input']) }}>
    @if($hint) <p id="{{ $name }}-help" class="field-help">{{ $hint }}</p> @endif
    @error($name) <p id="{{ $name }}-error" class="field-error">{{ $message }}</p> @enderror
</div>
