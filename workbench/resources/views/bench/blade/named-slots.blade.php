@for ($i = 0; $i < $iterations; $i++)
    <x-bench.blade.card-full>
        <x-slot:header>Header</x-slot:header>
        Card content
        <x-slot:footer>Footer</x-slot:footer>
    </x-bench.blade.card-full>
@endfor
