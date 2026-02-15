@blaze(fold: true)

@props(['value'])

<div>Computed: {{ json_decode($value, true)['key'] }}</div>
