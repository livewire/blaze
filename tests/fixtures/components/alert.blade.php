@props(['message' => null])

<div class="alert">{{ $message ?? $slot }}</div>