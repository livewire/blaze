@blaze

@props(['placeholder' => null])

<select class="select-default">
    @if($placeholder)
        <option value="" disabled>{{ $placeholder }}</option>
    @endif
    @isset($trigger)
        <div class="trigger">{{ $trigger }}</div>
    @endisset
    {{ $slot }}
</select>
