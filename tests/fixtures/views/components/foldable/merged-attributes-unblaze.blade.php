@blaze(fold: true, safe: ['title'])

@props(['title' => null])

<div>
    @unblaze(scope: ['attributes' => $attributes->merge(['title' => $title])->getAttributes()])
    <span {{ new \Illuminate\View\ComponentAttributeBag($scope['attributes']) }}></span>
    @endunblaze
</div>
