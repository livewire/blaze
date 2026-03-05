@blaze(fold: true, safe: ['name'])

@props(['name' => false])

<input
    @unblaze(scope: ['name' => $name])
    {{ $errors->has($scope['name']) }}
    @endunblaze
>