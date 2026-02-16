@blaze(fold: true)

<form method="POST">
    @csrf
    {{ $slot }}
</form>
