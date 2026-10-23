@blaze(fold: true)

@props(['label' => __('Save & close')])

<div>
    @unblaze(scope: ['label' => $label])
    {{ $scope['label'] }}
    @endunblaze
</div>
