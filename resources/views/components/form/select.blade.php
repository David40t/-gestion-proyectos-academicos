@props(['name', 'label', 'options', 'value' => null, 'placeholder' => 'Selecciona una opción'])
{{-- $options: colección/arreglo [valor => texto] --}}
<div class="field">
    <label for="{{ $name }}">{{ $label }}</label>
    <select id="{{ $name }}" name="{{ $name }}" {{ $attributes->class(['is-invalid' => $errors->has($name)]) }}>
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @error($name)
        <p class="field-error">{{ $message }}</p>
    @enderror
</div>
