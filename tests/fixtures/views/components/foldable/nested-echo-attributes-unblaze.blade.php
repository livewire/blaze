@blaze(fold: true, safe: ['title'])

@props(['title' => null])

<section>
    <x-foldable.attributes-unblaze title="{{ $title }}" />
</section>
