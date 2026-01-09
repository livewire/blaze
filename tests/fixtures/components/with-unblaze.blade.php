@blaze(fold: true)
<div class="container">
    <h1>Static Header</h1>
    @unblaze
        <p>Dynamic content: {{ $dynamicValue }}</p>
    @endunblaze
    <footer>Static Footer</footer>
</div>
