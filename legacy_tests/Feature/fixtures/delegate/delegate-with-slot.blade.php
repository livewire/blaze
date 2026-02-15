@blaze

@props(['type' => 'button'])

<flux:delegate-component :component="'button.' . $type">
    <x-slot:icon>
        <span>Icon</span>
    </x-slot:icon>
    Button Text
</flux:delegate-component>
