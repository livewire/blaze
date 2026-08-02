@blaze(fold: true, unsafe: ['footer'])

<div>
    {{ $header ?? 'Default' }}
    <hr>
    {{ $slot ?? 'Default' }}
    <hr>
    {{ $footer ?? 'Default' }}
</div>