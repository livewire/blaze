@blaze(memo: true)

@props(['src'])

<img src="{{ $src ?? 'https://placeholder.com/100/100' }}" alt="Avatar">