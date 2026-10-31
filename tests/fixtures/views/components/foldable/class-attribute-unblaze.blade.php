@blaze(fold: true)

<div>
    @unblaze(scope: ['attributes' => $attributes->class($attributes->get('class'))->getAttributes()])
    <span {{ new \Illuminate\View\ComponentAttributeBag($scope['attributes']) }}></span>
    @endunblaze
</div>
