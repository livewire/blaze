@props([
    'name' => null,
])

@php
$message = isset($errors) ? $errors->first($name) : null;
@endphp

<div {{ $attributes->class($message ? 'mt-3 text-sm font-medium text-red-500 dark:text-red-400' : 'hidden') }}>
    <?php if ($message) : ?>
        {{ $message }}
    <?php endif; ?>
</div>
