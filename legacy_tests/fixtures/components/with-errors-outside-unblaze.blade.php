@blaze(fold: true)
<div class="form-input">
    <label>Email</label>
    <input type="email" name="email">
    @if($errors->has('email'))
        <span class="error">{{ $errors->first('email') }}</span>
    @endif
</div>
