@blaze(fold: true)

@unblaze(scope: ['attributes' => $attributes->getAttributes()])
<div {{ new \Illuminate\View\ComponentAttributeBag($scope['attributes']) }}></div>
@endunblaze
