@blaze(fold: true)
<div class="form-wrapper">
    <h2>Form</h2>
    @unblaze
        <form method="POST">
            @csrf
            <button>Submit</button>
        </form>
    @endunblaze
</div>
