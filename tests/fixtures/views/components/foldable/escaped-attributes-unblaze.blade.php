@blaze(fold: true)

<div>
    @unblaze(scope: ['title' => e($attributes->get('title'))])
    <span>{!! $scope['title'] !!}</span>
    @endunblaze
</div>
