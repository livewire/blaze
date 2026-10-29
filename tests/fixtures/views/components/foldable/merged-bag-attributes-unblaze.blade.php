@blaze(fold: true)

<div>
    @unblaze(scope: ['attributes' => (new \Illuminate\View\ComponentAttributeBag)->merge($attributes->getAttributes())->getAttributes()])
    <button {{ new \Illuminate\View\ComponentAttributeBag($scope['attributes']) }}>Submit</button>
    @endunblaze
</div>
