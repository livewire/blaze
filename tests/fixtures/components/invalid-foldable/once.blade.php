@blaze(fold: true)

@props(['title'])

<div class="test-component">
    <h1>{{ $title }}</h1>
    @once
        <script>console.log('This should only run once');</script>
    @endonce
</div>
