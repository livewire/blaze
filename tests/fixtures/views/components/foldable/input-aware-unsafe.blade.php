@blaze(fold: true)

@aware(['type' => 'text'])

@if ($type == 'number')
    <input type="number">
@else
    <input type="text">
@endif