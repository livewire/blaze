@blaze(fold: true, safe: ['title'])

@props(['title' => null])

<div>
    @unblaze(scope: ['title' => e($title, false)])
    <span>{!! $scope['title'] !!}</span>
    @endunblaze
</div>
