@blaze(fold: true, safe: ['title'])

@props(['title' => null])

<div>
    @unblaze(scope: ['prop' => $title, 'attribute' => $attributes->get('label')])
    <span>{{ $scope['prop'] }}</span>
    <span>{{ $scope['attribute'] }}</span>
    @endunblaze
</div>
