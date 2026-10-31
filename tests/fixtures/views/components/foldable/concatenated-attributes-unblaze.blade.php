@blaze(fold: true, safe: ['name'])

@props(['name' => null])

<div>
    @unblaze(scope: ['id' => 'field_' . $name, 'label' => $attributes->get('label') . '!'])
    <span id="{{ $scope['id'] }}" data-label="{{ $scope['label'] }}"></span>
    @endunblaze
</div>
