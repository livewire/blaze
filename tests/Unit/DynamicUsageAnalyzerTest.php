<?php

use Livewire\Blaze\Folder\DynamicUsageAnalyzer;

describe('canFold with no dynamic props', function () {
    it('allows folding when no dynamic props', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["name"]) <div>{{ $name }}</div>';

        expect($analyzer->canFold($source, []))->toBeTrue();
    });
});

describe('canFold with simple echoes', function () {
    it('allows folding when prop is only echoed simply', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["name"]) <div>{{ $name }}</div>';

        expect($analyzer->canFold($source, ['name']))->toBeTrue();
    });

    it('allows folding with unescaped echo', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["html"]) <div>{!! $html !!}</div>';

        expect($analyzer->canFold($source, ['html']))->toBeTrue();
    });

    it('allows folding with multiple simple echoes', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["name", "title"]) <div>{{ $name }} - {{ $title }}</div>';

        expect($analyzer->canFold($source, ['name', 'title']))->toBeTrue();
    });
});

describe('canFold with PHP blocks', function () {
    it('aborts when prop is used in @php block', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["name"]) @php $x = trim($name); @endphp <div>{{ $x }}</div>';

        expect($analyzer->canFold($source, ['name']))->toBeFalse();
    });

    it('aborts when prop is used in standard PHP block', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["name"]) <?php $x = $name; ?> <div>{{ $x }}</div>';

        expect($analyzer->canFold($source, ['name']))->toBeFalse();
    });

    it('allows when different prop is in PHP block', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["name", "other"]) @php $x = $other; @endphp <div>{{ $name }}</div>';

        expect($analyzer->canFold($source, ['name']))->toBeTrue();
    });
});

describe('canFold with Blade directives', function () {
    it('aborts when prop is used in @if', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["show"]) @if($show) visible @endif';

        expect($analyzer->canFold($source, ['show']))->toBeFalse();
    });

    it('aborts when prop is used in @foreach', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["items"]) @foreach($items as $item) {{ $item }} @endforeach';

        expect($analyzer->canFold($source, ['items']))->toBeFalse();
    });

    it('aborts when prop is used in @isset', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["name"]) @isset($name) {{ $name }} @endisset';

        expect($analyzer->canFold($source, ['name']))->toBeFalse();
    });

    it('aborts when prop is used in @unless', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["hidden"]) @unless($hidden) visible @endunless';

        expect($analyzer->canFold($source, ['hidden']))->toBeFalse();
    });

    it('aborts when prop is used in custom directive', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["value"]) @customDirective($value) content @endcustomDirective';

        expect($analyzer->canFold($source, ['value']))->toBeFalse();
    });

    it('allows when prop is in @props directive itself', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["name" => "default"]) <div>{{ $name }}</div>';

        expect($analyzer->canFold($source, ['name']))->toBeTrue();
    });

    it('allows when prop is in @aware directive', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["variant"]) @aware(["variant"]) <div>{{ $variant }}</div>';

        expect($analyzer->canFold($source, ['variant']))->toBeTrue();
    });
});

describe('canFold with transformed echoes', function () {
    it('aborts when prop has null coalesce', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["name"]) <div>{{ $name ?? "Guest" }}</div>';

        expect($analyzer->canFold($source, ['name']))->toBeFalse();
    });

    it('aborts when prop is passed as function argument', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // Function call transforms the prop value at compile-time, corrupting placeholders
        $source = '@props(["name"]) <div>{{ strtoupper($name) }}</div>';

        expect($analyzer->canFold($source, ['name']))->toBeFalse();
    });

    it('aborts when prop has method call ON it', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["date"]) <div>{{ $date->format("Y-m-d") }}</div>';

        expect($analyzer->canFold($source, ['date']))->toBeFalse();
    });

    it('aborts when prop has array access ON it', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["data"]) <div>{{ $data["key"] }}</div>';

        expect($analyzer->canFold($source, ['data']))->toBeFalse();
    });

    it('aborts when prop has concatenation', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["name"]) <div>{{ $name . " suffix" }}</div>';

        expect($analyzer->canFold($source, ['name']))->toBeFalse();
    });

    it('aborts when prop has ternary', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["name"]) <div>{{ $name ? $name : "default" }}</div>';

        expect($analyzer->canFold($source, ['name']))->toBeFalse();
    });

    it('aborts when prop is in arithmetic expression', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["count"]) <div>{{ $count + 1 }}</div>';

        expect($analyzer->canFold($source, ['count']))->toBeFalse();
    });

    it('allows when prop is passed in method call argument', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // Prop passed as value in array - placeholder substitution works
        $source = '@props(["type"]) <button {{ $attributes->merge(["type" => $type]) }}></button>';

        expect($analyzer->canFold($source, ['type']))->toBeTrue();
    });

    it('aborts when prop is transformed inside $attributes merge', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // Prop is transformed by strtoupper() - this corrupts placeholders at compile-time
        $source = '@props(["type"]) <button {{ $attributes->merge(["class" => strtoupper($type)]) }}></button>';

        expect($analyzer->canFold($source, ['type']))->toBeFalse();
    });

    it('aborts when prop has elvis operator', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["name"]) <div>{{ $name ?: "default" }}</div>';

        expect($analyzer->canFold($source, ['name']))->toBeFalse();
    });
});

describe('canFold with $attributes bag', function () {
    it('allows when defined prop + $attributes echoed with methods', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["name"]) <div {{ $attributes->merge(["class" => "foo"]) }}>{{ $name }}</div>';

        expect($analyzer->canFold($source, ['name']))->toBeTrue();
    });

    it('allows when undefined prop + $attributes only echoed', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // 'src' is dynamic but NOT defined in @props, so it goes into $attributes
        // Since $attributes is only echoed (not used in PHP context), folding is safe
        $source = '@props(["name"]) <img {{ $attributes->merge([]) }} />';

        expect($analyzer->canFold($source, ['src']))->toBeTrue();
    });

    it('aborts when undefined prop + $attributes used in directive', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // $attributes used in @if directive expression - unsafe
        $source = '@props(["name"]) @if($attributes->has("src")) <img /> @endif';

        expect($analyzer->canFold($source, ['src']))->toBeFalse();
    });

    it('aborts when undefined prop + $attributes used in PHP block', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // $attributes used in @php block - unsafe
        $source = '@props(["name"]) @php $x = $attributes->get("src"); @endphp <img />';

        expect($analyzer->canFold($source, ['src']))->toBeFalse();
    });

    it('allows when no @props + $attributes only echoed', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // No @props, but $attributes only echoed - safe
        $source = '<div {{ $attributes->class("foo") }}>content</div>';

        expect($analyzer->canFold($source, ['name']))->toBeTrue();
    });

    it('aborts when no @props + $attributes in directive', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // No @props and $attributes used in directive - unsafe
        $source = '@if($attributes->has("class")) <div></div> @endif';

        expect($analyzer->canFold($source, ['name']))->toBeFalse();
    });

    it('allows when no @props + no $attributes usage', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '<div>{{ $name }}</div>';

        expect($analyzer->canFold($source, ['name']))->toBeTrue();
    });

    it('allows when $attributes is echoed without methods', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["name"]) <div {{ $attributes }}>{{ $name }}</div>';

        expect($analyzer->canFold($source, ['name']))->toBeTrue();
    });
});

describe('canFold with @unblaze blocks', function () {
    it('allows when prop is only used inside @unblaze', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["name"]) <div>@unblaze @if($name) {{ $name }} @endif @endunblaze</div>';

        expect($analyzer->canFold($source, ['name']))->toBeTrue();
    });

    it('aborts when prop is used both inside and outside @unblaze', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["name"]) @if($name) ok @endif @unblaze {{ $name }} @endunblaze';

        expect($analyzer->canFold($source, ['name']))->toBeFalse();
    });
});

describe('canFold edge cases', function () {
    it('handles props with similar names correctly', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // $name should not match $nameLong
        $source = '@props(["name", "nameLong"]) @if($nameLong) {{ $name }} @endif';

        expect($analyzer->canFold($source, ['name']))->toBeTrue();
    });

    it('handles empty @props with echoed $attributes', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props([]) <div {{ $attributes->merge([]) }}>content</div>';

        // Dynamic prop 'name' is not in @props([]), goes to $attributes
        // But $attributes is only echoed, so folding is safe
        expect($analyzer->canFold($source, ['name']))->toBeTrue();
    });

    it('handles empty @props with $attributes in directive', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props([]) @if($attributes->has("name")) <div></div> @endif';

        // Dynamic prop 'name' is not in @props([]), and $attributes is in directive
        expect($analyzer->canFold($source, ['name']))->toBeFalse();
    });

    it('handles multiline source', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = <<<'BLADE'
@props(['name', 'title'])

<div class="card">
    <h1>{{ $title }}</h1>
    <p>{{ $name }}</p>
</div>
BLADE;

        expect($analyzer->canFold($source, ['name', 'title']))->toBeTrue();
    });

    it('handles complex real-world component', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = <<<'BLADE'
@props(['src' => null, 'alt' => ''])

<img src="{{ $src }}" alt="{{ $alt }}" {{ $attributes->class('rounded') }} />
BLADE;

        // Both props are defined in @props and only simply echoed
        expect($analyzer->canFold($source, ['src', 'alt']))->toBeTrue();
    });

    it('aborts for flux-style avatar component with PHP manipulation', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = <<<'BLADE'
@props(['name' => null, 'src' => null])

@php
if ($name) {
    $parts = explode(' ', trim($name));
    $initials = count($parts) > 1 
        ? $parts[0][0] . $parts[1][0]
        : substr($name, 0, 2);
}
@endphp

<img src="{{ $src }}" alt="{{ $name }}" />
BLADE;

        // $src is safe (only echoed), but $name is used in PHP block
        expect($analyzer->canFold($source, ['src']))->toBeTrue();
        expect($analyzer->canFold($source, ['name']))->toBeFalse();
        expect($analyzer->canFold($source, ['src', 'name']))->toBeFalse();
    });

    it('allows button component with attributes merge', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = <<<'BLADE'
@props(['type' => 'button'])

<button {{ $attributes->merge(['type' => $type]) }}>{{ $slot }}</button>
BLADE;

        // $type is used in $attributes->merge() as a value, which is safe
        expect($analyzer->canFold($source, ['type']))->toBeTrue();
    });
});

describe('canFold with nested component attributes', function () {
    it('allows when prop passes through in component attribute', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["type"]) <x-button :type="$type" />';

        expect($analyzer->canFold($source, ['type']))->toBeTrue();
    });

    it('allows when prop passes through in flux component attribute', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["variant"]) <flux:button :variant="$variant" />';

        expect($analyzer->canFold($source, ['variant']))->toBeTrue();
    });

    it('aborts when prop is transformed by function in component attribute', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["type"]) <x-button :type="strtoupper($type)" />';

        expect($analyzer->canFold($source, ['type']))->toBeFalse();
    });

    it('aborts when prop is concatenated in component attribute', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = <<<'BLADE'
@props(["name"])
<x-card :title="$name . ' - Card'" />
BLADE;

        expect($analyzer->canFold($source, ['name']))->toBeFalse();
    });

    it('aborts when prop has null coalesce in component attribute', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = <<<'BLADE'
@props(["value"])
<x-input :value="$value ?? 'default'" />
BLADE;

        expect($analyzer->canFold($source, ['value']))->toBeFalse();
    });

    it('aborts when prop has array access in component attribute', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = <<<'BLADE'
@props(["data"])
<x-list :items="$data['items']" />
BLADE;

        expect($analyzer->canFold($source, ['data']))->toBeFalse();
    });

    it('aborts when prop has method call in component attribute', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["date"]) <x-display :formatted="$date->format(\'Y-m-d\')" />';

        expect($analyzer->canFold($source, ['date']))->toBeFalse();
    });

    it('allows multiple safe prop usages in nested components', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = <<<'BLADE'
@props(["type", "variant", "size"])
<x-wrapper :type="$type">
    <flux:button :variant="$variant" :size="$size" />
</x-wrapper>
BLADE;

        expect($analyzer->canFold($source, ['type', 'variant', 'size']))->toBeTrue();
    });

    it('aborts when one of many props is transformed in nested component', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = <<<'BLADE'
@props(["type", "label"])
<x-button :type="$type" :label="strtoupper($label)" />
BLADE;

        // type is safe, but label is transformed
        expect($analyzer->canFold($source, ['type']))->toBeTrue();
        expect($analyzer->canFold($source, ['label']))->toBeFalse();
        expect($analyzer->canFold($source, ['type', 'label']))->toBeFalse();
    });

    it('handles short syntax dynamic attributes', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // :$type is shorthand for :type="$type"
        $source = '@props(["type"]) <x-button :$type />';

        expect($analyzer->canFold($source, ['type']))->toBeTrue();
    });
});
