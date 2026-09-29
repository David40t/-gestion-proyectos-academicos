@props(['name', 'label', 'type' => 'text', 'value' => null])

<div class="field">
    <label for="{{ $name }}">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
           value="{{ $type === 'password' ? '' : old($name, $value) }}"
           {{ $attributes->class(['is-invalid' => $errors->has($name)]) }}>
    @error($name)
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>
