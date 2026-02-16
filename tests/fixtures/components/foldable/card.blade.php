@blaze(fold: true, unsafe: ['footer'])

<div>{{ $header ?? 'Default' }} | {{ $slot ?? 'Default' }} | {{ $footer ?? 'Default' }}</div>