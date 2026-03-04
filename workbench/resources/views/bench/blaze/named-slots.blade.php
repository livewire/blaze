@for ($i = 0; $i < $iterations; $i++)
    <x-bench.blaze.card-full>
        <x-slot:header>Header</x-slot:header>
        Card content
        <x-slot:footer>Footer</x-slot:footer>
    </x-bench.blaze.card-full>
@endfor
