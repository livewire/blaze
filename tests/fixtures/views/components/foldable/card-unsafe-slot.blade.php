@blaze(fold: true, unsafe: ['slot'])

<div>{{ $header ?? 'Default' }} | {{ $slot ?? 'Default' }} | {{ $footer ?? 'Default' }}</div>