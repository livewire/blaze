@blaze(fold: true, safe: ['name'])

@props(['name' => null])

<div>
    @unblaze(scope: ['attributes' => $attributes->getAttributes(), 'name' => $name])
    <span {{ new \Illuminate\View\ComponentAttributeBag($scope['attributes']) }} data-name="{{ $scope['name'] }}"></span>
    @endunblaze
</div>
