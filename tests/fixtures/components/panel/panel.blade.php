@pure

@props([])

<div class="panel">
    <div class="panel-header">
        {{ $header }}
    </div>
    <div class="panel-body">
        {{ $slot }}
    </div>
    <div class="panel-footer">
        {{ $footer }}
    </div>
</div>
