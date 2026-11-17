@props(['name', 'label', 'required' => false, 'col' => 'col-md-6', 'help' => null])

<div class="{{ $col }}">
    <label for="{{ $name }}" class="form-label fw-semibold">{{ $label }}@if ($required) <span class="text-danger">*</span>@endif</label>
    {{ $slot }}
    @error($name)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
