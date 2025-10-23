@blaze

@props([
    'name' => $attributes->whereStartsWith('wire:model')->first(),
])

<div>
    <div>{{ $slot }}</div>

    @imprint(['name' => $name])
        <x-error :name="$name" />
    @endimprint
</div>
