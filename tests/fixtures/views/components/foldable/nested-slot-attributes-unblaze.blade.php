@blaze(fold: true, safe: ['title'])

@props(['title' => null])

<x-foldable.slot-attributes-unblaze>
    <x-slot:header :title="$title">Header</x-slot:header>
</x-foldable.slot-attributes-unblaze>
