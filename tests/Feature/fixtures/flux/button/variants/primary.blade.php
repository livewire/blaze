@blaze

@props(['size' => 'md'])

<button class="btn btn-primary btn-{{ $size }}">{{ $slot }}</button>
