@blaze(fold: true)
<div class="scoped-component">
    <h1>Static: {{ \Illuminate\Support\Str::random(15) }}</h1>
    @unblaze(scope: ['value' => $value])
        <div class="dynamic-section">Value: {{ $scope['value'] }}</div>
    @endunblaze
    <p>Static paragraph: {{ \Illuminate\Support\Str::random(10) }}</p>
</div>
