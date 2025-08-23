@pure

@props([])

<form>
    <div class="form-header">
        {{ $header }}
    </div>
    <div class="form-body">
        {{ $slot }}
    </div>
    <div class="form-footer">
        {{ $footer }}
    </div>
</form>
