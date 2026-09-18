@for ($i = 0; $i < $iterations; $i++)
    <x-bench.blaze.alert :message="'msg-'.$i" />
@endfor