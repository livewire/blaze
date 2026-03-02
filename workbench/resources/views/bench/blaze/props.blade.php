@for ($i = 0; $i < $iterations; $i++)
    <x-bench.blaze.button-props type="submit" variant="secondary" data-index="{{ $i }}" />
@endfor
