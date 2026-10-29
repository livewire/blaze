@blaze(fold: true, safe: ['type'])

@aware(['type' => 'text'])

<div>
    @unblaze(scope: ['type' => $type])
    <span data-type="{{ $scope['type'] }}"></span>
    @endunblaze
</div>
