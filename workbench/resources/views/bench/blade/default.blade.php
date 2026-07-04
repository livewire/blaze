@for ($i = 0; $i < $iterations; $i++)
    <x-bench.blade.button-props type="submit" variant="secondary" data-index="{{ $i }}" />
@endfor
