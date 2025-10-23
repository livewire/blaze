@blaze

@props(['name'])

<div>
    @error($name)
        <span class="error">{{ $message }}</span>
    @enderror
</div>
