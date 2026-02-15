@blaze

<div class="errors">
@if($errors->any())
    @foreach($errors->all() as $error)
        <p class="error">{{ $error }}</p>
    @endforeach
@else
    <p class="no-errors">No errors</p>
@endif
</div>