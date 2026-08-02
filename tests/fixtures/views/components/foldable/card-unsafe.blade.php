@blaze(fold: true, unsafe: ['*'])

<div>{{ $header ?? 'Default' }} | {{ $slot ?? 'Default' }} | {{ $footer ?? 'Default' }}</div>