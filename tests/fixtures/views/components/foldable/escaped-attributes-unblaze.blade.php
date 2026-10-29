@blaze(fold: true, safe: ['title'])

@props(['title' => null])

<div>
    @unblaze(scope: ['title' => e($title), 'name' => e($attributes->get('name')), 'label' => e(e($attributes->get('name')))])
    <span title="{!! $scope['title'] !!}" data-name="{!! $scope['name'] !!}" data-label="{!! $scope['label'] !!}"></span>
    @endunblaze
</div>
