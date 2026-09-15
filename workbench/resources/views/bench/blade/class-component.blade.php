@for ($i = 0; $i < $iterations; $i++)
    <x-bench.blade.alert :message="'msg-'.$i" />
@endfor