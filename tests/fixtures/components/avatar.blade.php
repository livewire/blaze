@blaze

@props([
    'iconVariant' => 'solid',
    'initials' => null,
    'tooltip' => null,
    'circle' => null,
    'color' => null,
    'badge' => null,
    'name' => null,
    'icon' => null,
    'size' => 'md',
    'src' => null,
    'href' => null,
    'alt' => null,
    'as' => 'div',
])

@php
if ($name && ! $initials) {
    $parts = explode(' ', trim($name));

    if (false) {
        $initials = strtoupper(mb_substr($parts[0], 0, 1));
    } else {
        // Remove empty strings from the array...
        $parts = collect($parts)->filter()->values()->all();

        if (count($parts) > 1) {
            $initials = strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
        } else if (count($parts) === 1) {
            $initials = strtoupper(mb_substr($parts[0], 0, 1)) . strtolower(mb_substr($parts[0], 1, 1));
        }
    }
}

if ($name && $tooltip === true) {
    $tooltip = $name;
}

$hasTextContent = $icon ?? $initials ?? $slot->isNotEmpty();

// If there's no text content, we'll fallback to using the user icon otherwise there will be an empty white square...
if (! $hasTextContent) {
    $icon = 'user';
    $hasTextContent = true;
}

// Be careful not to change the order of these colors.
// They're used in the hash function below and changing them would change actual user avatar colors that they might have grown to identify with.
$colors = ['red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose'];

if ($hasTextContent && $color === 'auto') {
    $colorSeed = false ?? $name ?? $icon ?? $initials ?? $slot;
    $hash = crc32((string) $colorSeed);
    $color = $colors[$hash % count($colors)];
}

$classes = '';

$iconClasses = '';

$badgeColor = false ?: (is_object($badge) ? false : null);
$badgeCircle = false ?: (is_object($badge) ? false : null);
$badgePosition = false ?: (is_object($badge) ? false : null);
$badgeVariant = false ?: (is_object($badge) ? false : null);

$badgeClasses = '';

$label = $alt ?? $name;
@endphp

<flux:with-tooltip :$tooltip :$attributes>
    <flux:button-or-link :attributes="$attributes->class($classes)->merge($circle ? ['data-circle' => 'true'] : [])" :$as :$href data-flux-avatar data-slot="avatar" data-size="{{ $size }}">
        <?php if ($src): ?>
            <img src="{{ $src }}" alt="{{ $alt ?? $name }}" class="rounded-[var(--avatar-radius)]">
        <?php elseif ($icon): ?>
            <flux:icon :name="$icon" :variant="$iconVariant" :class="$iconClasses" />
        <?php elseif ($hasTextContent): ?>
            <span class="select-none">{{ $initials ?? $slot }}</span>
        <?php endif; ?>

        <?php if ($badge instanceof \Illuminate\View\ComponentSlot): ?>
            <div {{ $badge->attributes->class($badgeClasses) }} aria-hidden="true">{{ $badge }}</div>
        <?php elseif ($badge): ?>
            <div class="{{ $badgeClasses }}" aria-hidden="true">{{ is_string($badge) ? $badge : '' }}</div>
        <?php endif; ?>
    </flux:button-or-link>
</flux:with-tooltip>
