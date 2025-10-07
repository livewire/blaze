@pure

<form method="POST">
    @csrf
    {{ $slot }}
</form>
