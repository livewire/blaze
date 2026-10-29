@blaze(fold: true, safe: ['title'])

@props(['title' => null])

<section>
    <x-foldable.blade-attributes-unblaze :title="$title" />
</section>
