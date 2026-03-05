@blaze(fold: true)

<div class="{{ $errors->has('name') ? 'error' : '' }}">
    {{ $slot }}
</div>
