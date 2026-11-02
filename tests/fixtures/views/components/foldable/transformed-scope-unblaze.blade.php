@blaze(fold: true, safe: ['name'])

@props(['name'])

<div>
    @unblaze(scope: ['id' => 'field_' . $name])
    <span id="{{ $scope['id'] }}"></span>
    @endunblaze
</div>
