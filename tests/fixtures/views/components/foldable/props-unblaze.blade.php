@blaze(fold: true, safe: ['title'])

@props(['title'])

<div>
    @unblaze(scope: ['title' => $title])
    <span>{{ $scope['title'] }}</span>
    @endunblaze
</div>
