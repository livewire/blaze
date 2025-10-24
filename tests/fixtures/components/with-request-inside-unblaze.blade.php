@blaze
<nav>
    <a href="/home">Home</a>
    @unblaze
        <a href="/about" class="{{ request()->is('about') ? 'active' : '' }}">About</a>
    @endunblaze
</nav>
