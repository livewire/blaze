@blaze(fold: true)

<div>
    @unblaze(scope: ['title' => strtoupper($attributes->get('title'))])
    <span data-title="{!! $scope['title'] !!}"></span>
    @endunblaze
</div>
