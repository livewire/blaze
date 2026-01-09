@blaze(fold: true)

@aware(['variant', 'secondVariant' => null ])

<div class="item item-{{ $variant }}{{ $secondVariant ? ' item-'.$secondVariant : '' }}"></div>