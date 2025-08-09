@pure

@props([])

<div class="modal">
    <div class="modal-header">{{ $header }}</div>
    <div class="modal-body">{{ $slot }}</div>
    <div class="modal-footer">{{ $footer }}</div>
</div>