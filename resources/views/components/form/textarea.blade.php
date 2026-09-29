@props(['name', 'label', 'value' => null, 'rows' => 4])

<div class="field">
    <label for="{{ $name }}">{{ $label }}</label>
    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}"
              {{ $attributes->class(['is-invalid' => $errors->has($name)]) }}>{{ old($name, $value) }}</textarea>
    @error($name)
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>
