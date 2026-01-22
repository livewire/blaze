@blaze(fold: true, safe: ['name'])

@props(['name' => 'default', 'title' => ''])

<div class="modal" data-name="{{ $name }}">
    <div class="modal-title">{{ $title }}</div>
    <div class="modal-body">{{ $slot }}</div>
</div>
