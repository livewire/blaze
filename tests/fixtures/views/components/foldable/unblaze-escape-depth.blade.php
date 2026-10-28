@blaze(fold: true)

<div>
    @unblaze(scope: ['once' => $attributes->get('title'), 'twice' => e($attributes->get('title')), 'triple' => e(e($attributes->get('title')))])
    <span data-once="{!! $scope['once'] !!}" data-twice="{!! $scope['twice'] !!}" data-triple="{!! $scope['triple'] !!}"></span>
    @endunblaze
</div>
