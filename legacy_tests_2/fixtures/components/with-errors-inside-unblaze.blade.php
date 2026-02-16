@blaze(fold: true)
<div class="form-input">
    <label>Email</label>
    <input type="email" name="email">
    @unblaze
        @if($errors->has('email'))
            <span class="error">{{ $errors->first('email') }}</span>
        @endif
    @endunblaze
</div>
