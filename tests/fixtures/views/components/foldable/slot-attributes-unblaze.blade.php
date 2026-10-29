@blaze

<div>
    @unblaze(scope: ['attributes' => $header->attributes->getAttributes()])
    <span {{ new \Illuminate\View\ComponentAttributeBag($scope['attributes']) }}></span>
    @endunblaze
</div>
