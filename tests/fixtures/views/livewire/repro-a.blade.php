<div data-repro-boundary="A">
    <h2>A component</h2>

    @island('a', always: true)
        <div data-repro-marker="A-ISLAND">A island sees foo={{ $foo }}</div>
    @endisland
</div>

{{-- PAD_A=144792 --}}
