<div>
    {{ $node['label'] }}

    @if (! empty($node['children']))
        <ul>
            @foreach ($node['children'] as $child)
                <x-tree :node="$child" />
            @endforeach
        </ul>
    @endif
</div>