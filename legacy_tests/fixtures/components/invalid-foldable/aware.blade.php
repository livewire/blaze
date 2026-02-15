@blaze(fold: true)

@aware(['variant' => 'default'])

<div class="item item-{{ $variant }}">
    {{ $slot }}
</div>
