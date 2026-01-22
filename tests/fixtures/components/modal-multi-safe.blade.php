@blaze(fold: true, safe: ['name', 'id'])

@props(['name' => 'default', 'id' => '', 'title' => ''])

<div class="modal" data-name="{{ $name }}" data-id="{{ $id }}">
    <div class="modal-title">{{ $title }}</div>
    <div class="modal-body">{{ $slot }}</div>
</div>
