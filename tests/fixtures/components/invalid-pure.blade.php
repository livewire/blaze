@pure
<div class="{{ $errors->has('name') ? 'error' : '' }}">
    {{ $slot }}
</div>