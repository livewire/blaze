@blaze(fold: true)

@php
if ($wireClick = $attributes->whereStartsWith('wire:click')->first()) {
    $attributes = $attributes->merge(['wire:target' => $wireClick], escape: false);
}
@endphp

<button {{ $attributes }} type="button"></button>
