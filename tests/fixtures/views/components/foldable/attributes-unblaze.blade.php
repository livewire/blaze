@blaze(fold: true)

<div
    @unblaze(scope: ['attributes' => $attributes->getAttributes()])
    {{ new \Illuminate\View\ComponentAttributeBag($scope['attributes']) }}
    @endunblaze
></div>
