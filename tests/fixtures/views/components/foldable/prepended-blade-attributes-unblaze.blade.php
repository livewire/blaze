@blaze(fold: true)

@php $bag = new \Illuminate\View\ComponentAttributeBag($attributes->getAttributes()); @endphp

<div>
    @unblaze(scope: ['attributes' => $bag->merge(['data-action' => $bag->prepends($bag->get('data-action'))])->getAttributes()])
    <button {{ new \Illuminate\View\ComponentAttributeBag($scope['attributes']) }}>Save</button>
    @endunblaze
</div>
