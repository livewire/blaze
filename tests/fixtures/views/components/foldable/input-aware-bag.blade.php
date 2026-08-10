@blaze(fold: true)

{{-- A component that has to tell an attribute written on its own tag from one it
     inherited. The field name on a form control is the usual case: it decides
     whether the control owns its own validation message or whether the field
     around it does. The bag is snapshotted and restored because @aware consumes
     the key, and the value still has to reach the element. --}}
@php
    $__bag = $attributes->getAttributes();
@endphp

@aware(['type' => 'text'])

@php
    $attributes->setAttributes($__bag);
@endphp

<input type="{{ $type }}" data-source="{{ $attributes->get('type') === null ? 'inherited' : 'own' }}" >
