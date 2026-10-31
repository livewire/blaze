@blaze(fold: true, safe: ['title'])

@props(['title' => null])

<div>
    @unblaze(scope: ['title' => e($title)])
    <span title="{!! $scope['title'] !!}"></span>
    @endunblaze
</div>
