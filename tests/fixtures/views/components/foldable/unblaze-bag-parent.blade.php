@blaze(fold: true, safe: ['title'])

@props(['title' => null])

<section>
    <x-foldable.unblaze-bag :title="$title" :name="$title" />
</section>
