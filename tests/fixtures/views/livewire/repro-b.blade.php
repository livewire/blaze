<div data-repro-boundary="B">
    <h2>B component</h2>

    @island('b', always: true)
        <div data-repro-marker="B-ISLAND">B island sees bar={{ $bar }}</div>
    @endisland
</div>

{{-- PAD_B=2748 --}}
