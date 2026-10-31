<div>
    @unblaze(scope: ['attributes' => $attributes->getAttributes()])
    <span {{ new \Illuminate\View\ComponentAttributeBag($scope['attributes']) }}></span>
    @endunblaze
</div>
