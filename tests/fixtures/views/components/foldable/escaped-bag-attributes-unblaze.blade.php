@blaze(fold: true)

<div>
    @unblaze(scope: ['name' => e($attributes->get('name')), 'label' => e(e($attributes->get('name')))])
    <span data-name="{!! $scope['name'] !!}" data-label="{!! $scope['label'] !!}"></span>
    @endunblaze
</div>
