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
    it('aborts when prop has null coalesce on left side', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // Prop on LEFT side - being tested, could be replaced by fallback
        $source = '@props(["name"]) <div>{{ $name ?? "Guest" }}</div>';

        expect($analyzer->canFold($source, ['name']))->toBeFalse();
    });

    it('allows prop on right side of null coalesce', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // Prop on RIGHT side - used as fallback, passes through unchanged
        $source = '@props(["name"]) <div>{{ $other ?? $name }}</div>';

        expect($analyzer->canFold($source, ['name']))->toBeTrue();
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

    it('allows when prop has concatenation', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["name"]) <div>{{ $name . " suffix" }}</div>';

        expect($analyzer->canFold($source, ['name']))->toBeTrue();
    });

    it('aborts when prop is used as ternary condition', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // Prop in CONDITION position - value is tested, affects control flow
        $source = '@props(["name"]) <div>{{ $name ? $name : "default" }}</div>';

        expect($analyzer->canFold($source, ['name']))->toBeFalse();
    });

    it('allows prop in ternary else branch only', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // Prop only in ELSE branch - passes through unchanged when condition is false
        $source = '@props(["fallback"]) <div>{{ $other ? "yes" : $fallback }}</div>';

        expect($analyzer->canFold($source, ['fallback']))->toBeTrue();
    });

    it('allows prop in ternary if branch only', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // Prop only in IF branch - passes through unchanged when condition is true
        $source = '@props(["value"]) <div>{{ $condition ? $value : "default" }}</div>';

        expect($analyzer->canFold($source, ['value']))->toBeTrue();
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

    it('allows when prop is concatenated in component attribute', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = <<<'BLADE'
@props(["name"])
<x-card :title="$name . ' - Card'" />
BLADE;

        expect($analyzer->canFold($source, ['name']))->toBeTrue();
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

// Helper to get fixture path
function fixturePath(string $name): string
{
    return __DIR__ . '/fixtures/nested/' . $name . '.blade.php';
}

describe('canFold with nested prop forwarding', function () {
    it('allows when child only echoes forwarded prop', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $parentSource = '@props(["name"]) <x-child :title="$name" />';

        $componentNameToPath = fn ($name) => $name === 'child' ? fixturePath('child-echoes-only') : null;

        expect($analyzer->canFold($parentSource, ['name'], $componentNameToPath))->toBeTrue();
    });

    it('aborts when child uses forwarded prop in @if', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $parentSource = '@props(["name"]) <x-child :title="$name" />';

        $componentNameToPath = fn ($name) => $name === 'child' ? fixturePath('child-uses-if') : null;

        expect($analyzer->canFold($parentSource, ['name'], $componentNameToPath))->toBeFalse();
    });

    it('aborts when child uses forwarded prop in @php', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $parentSource = '@props(["name"]) <x-child :title="$name" />';

        $componentNameToPath = fn ($name) => $name === 'child' ? fixturePath('child-uses-php') : null;

        expect($analyzer->canFold($parentSource, ['name'], $componentNameToPath))->toBeFalse();
    });

    it('handles prop name mapping (parent $name -> child $title)', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $parentSource = '@props(["name"]) <x-child :title="$name" />';

        // Child uses $title in @if - should fail
        $componentNameToPath = fn ($name) => $name === 'child' ? fixturePath('child-uses-if') : null;

        expect($analyzer->canFold($parentSource, ['name'], $componentNameToPath))->toBeFalse();
    });

    it('handles short syntax :$prop', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // :$type is shorthand for :type="$type"
        $parentSource = '@props(["type"]) <x-child :$type />';

        $componentNameToPath = fn ($name) => $name === 'child' ? fixturePath('child-type-if') : null;

        expect($analyzer->canFold($parentSource, ['type'], $componentNameToPath))->toBeFalse();
    });

    it('allows when static prop does not affect child analysis', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // title is static, name is dynamic - name is not forwarded to title
        $parentSource = '@props(["name"]) <x-child title="static" :other="$name" />';

        $componentNameToPath = fn ($name) => $name === 'child' ? fixturePath('child-uses-if') : null;

        // name is not forwarded to child's title at all
        expect($analyzer->canFold($parentSource, ['name'], $componentNameToPath))->toBeTrue();
    });

    it('skips analysis for non-Blaze children', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $parentSource = '@props(["name"]) <x-child :title="$name" />';

        // Child has NO @blaze directive
        $componentNameToPath = fn ($name) => $name === 'child' ? fixturePath('child-non-blaze') : null;

        // Non-Blaze children are skipped (optimistic approach)
        expect($analyzer->canFold($parentSource, ['name'], $componentNameToPath))->toBeTrue();
    });

    it('skips analysis for Blaze children with fold: false', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $parentSource = '@props(["name"]) <x-child :title="$name" />';

        // Child has @blaze but fold: false
        $componentNameToPath = fn ($name) => $name === 'child' ? fixturePath('child-fold-false') : null;

        expect($analyzer->canFold($parentSource, ['name'], $componentNameToPath))->toBeTrue();
    });

    it('skips analysis for unresolvable children', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $parentSource = '@props(["name"]) <x-unknown-child :title="$name" />';

        // Callback returns null for unknown components
        $componentNameToPath = fn ($name) => null;

        expect($analyzer->canFold($parentSource, ['name'], $componentNameToPath))->toBeTrue();
    });

    it('handles multiple props forwarded to same child', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $parentSource = '@props(["name", "variant"]) <x-child :title="$name" :size="$variant" />';

        // Child uses both props, size in @if
        $componentNameToPath = fn ($name) => $name === 'child' ? fixturePath('child-multi-props') : null;

        // $variant mapped to $size is used in @if
        expect($analyzer->canFold($parentSource, ['name', 'variant'], $componentNameToPath))->toBeFalse();
    });

    it('handles same prop forwarded to multiple children', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $parentSource = '@props(["name"]) <x-safe-child :value="$name" /> <x-unsafe-child :value="$name" />';

        $componentNameToPath = fn ($name) => match($name) {
            'safe-child' => fixturePath('child-safe-value'),
            'unsafe-child' => fixturePath('child-unsafe-value'),
            default => null,
        };

        expect($analyzer->canFold($parentSource, ['name'], $componentNameToPath))->toBeFalse();
    });

    it('aborts if ANY child fails analysis', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $parentSource = '@props(["x", "y"]) <x-safe :value="$x" /> <x-unsafe :value="$y" />';

        $componentNameToPath = fn ($name) => match($name) {
            'safe' => fixturePath('child-safe-value'),
            'unsafe' => fixturePath('child-unsafe-value'),
            default => null,
        };

        expect($analyzer->canFold($parentSource, ['x', 'y'], $componentNameToPath))->toBeFalse();
    });
});

describe('canFold with recursive nested analysis', function () {
    it('analyzes grandchildren recursively', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $parentSource = '@props(["top"]) <x-child :mid="$top" />';

        $componentNameToPath = fn ($name) => match($name) {
            'child' => fixturePath('child-forwards-to-grandchild'),
            'grandchild' => fixturePath('grandchild-unsafe'),
            default => null,
        };

        expect($analyzer->canFold($parentSource, ['top'], $componentNameToPath))->toBeFalse();
    });

    it('handles deep nesting (3+ levels)', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $parentSource = '@props(["v0"]) <x-level1 :v1="$v0" />';

        $componentNameToPath = fn ($name) => match($name) {
            'level1' => fixturePath('level1'),
            'level2' => fixturePath('level2'),
            'level3' => fixturePath('level3'),
            default => null,
        };

        expect($analyzer->canFold($parentSource, ['v0'], $componentNameToPath))->toBeFalse();
    });

    it('handles cycle detection', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $parentSource = '@props(["prop"]) <x-cycle-a :val="$prop" />';

        $componentNameToPath = fn ($name) => match($name) {
            'cycle-a' => fixturePath('cycle-a'),
            'cycle-b' => fixturePath('cycle-b'),
            default => null,
        };

        // Should not hang due to cycle detection
        expect($analyzer->canFold($parentSource, ['prop'], $componentNameToPath))->toBeTrue();
    });
});

describe('canFold with nested $attributes forwarding', function () {
    it('allows when child only echoes $attributes', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // Parent forwards all attributes to child
        $parentSource = '@props([]) <x-child :$attributes />';

        $componentNameToPath = fn ($name) => $name === 'child' ? fixturePath('child-echoes-attributes') : null;

        expect($analyzer->canFold($parentSource, ['class'], $componentNameToPath))->toBeTrue();
    });

    it('aborts when child uses $attributes in directive', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $parentSource = '@props([]) <x-child :$attributes />';

        $componentNameToPath = fn ($name) => $name === 'child' ? fixturePath('child-uses-attributes-in-directive') : null;

        expect($analyzer->canFold($parentSource, ['class'], $componentNameToPath))->toBeFalse();
    });

    it('respects except filtering', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // Parent excludes 'class' from forwarding
        $parentSource = '@props([]) <x-child :attributes="$attributes->except([\'class\'])" />';

        // Child uses $id in @if (which is fine because 'class' is excluded)
        $componentNameToPath = fn ($name) => $name === 'child' ? fixturePath('child-uses-id-in-if') : null;

        // class is excluded, so dynamic 'class' prop doesn't reach child
        expect($analyzer->canFold($parentSource, ['class'], $componentNameToPath))->toBeTrue();
    });

    it('tracks props through merge rename', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // Parent renames $variant to 'type' via merge
        $parentSource = '@props(["variant"]) <x-child :attributes="$attributes->merge([\'type\' => $variant])" />';

        // Child uses $type in @if
        $componentNameToPath = fn ($name) => $name === 'child' ? fixturePath('child-uses-type-in-if') : null;

        expect($analyzer->canFold($parentSource, ['variant'], $componentNameToPath))->toBeFalse();
    });

    it('aborts when $attributes chain is unanalyzable', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // filter() is not in the allow-list
        $parentSource = '@props([]) <x-child :attributes="$attributes->filter(fn ($v) => $v)" />';

        $componentNameToPath = fn ($name) => $name === 'child' ? fixturePath('child-echoes-attributes') : null;

        expect($analyzer->canFold($parentSource, ['class'], $componentNameToPath))->toBeFalse();
    });

    it('handles :attributes="$attributes" syntax', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // Explicit :attributes="$attributes"
        $parentSource = '@props([]) <x-child :attributes="$attributes" />';

        $componentNameToPath = fn ($name) => $name === 'child' ? fixturePath('child-uses-attributes-in-directive') : null;

        expect($analyzer->canFold($parentSource, ['id'], $componentNameToPath))->toBeFalse();
    });
});

describe('canFold with mixed forwarding', function () {
    it('handles both prop and $attributes forwarding to same child', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // Parent forwards $name as :title and $attributes (which includes $class)
        $parentSource = '@props(["name"]) <x-child :title="$name" :$attributes />';

        // Child uses both $title in @if and $attributes
        $componentNameToPath = fn ($name) => $name === 'child' ? fixturePath('child-mixed') : null;

        expect($analyzer->canFold($parentSource, ['name', 'class'], $componentNameToPath))->toBeFalse();
    });

    it('allows when individual prop is safe and $attributes is safe', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $parentSource = '@props(["name"]) <x-child :title="$name" :$attributes />';

        // Child only echoes $title and $attributes (no directives)
        $componentNameToPath = fn ($name) => $name === 'child' ? fixturePath('child-safe-mixed') : null;

        expect($analyzer->canFold($parentSource, ['name', 'class'], $componentNameToPath))->toBeTrue();
    });
});

describe('canFold without componentNameToPath callback', function () {
    it('skips nested analysis when callback is null', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // Even if child would fail, without callback no nested analysis happens
        $parentSource = '@props(["name"]) <x-dangerous-child :title="$name" />';

        // No callback = no nested analysis
        expect($analyzer->canFold($parentSource, ['name']))->toBeTrue();
        expect($analyzer->canFold($parentSource, ['name'], null))->toBeTrue();
    });
});

describe('canFold with slot variables', function () {
    it('allows when $slot is only echoed simply', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '<div>{{ $slot }}</div>';

        expect($analyzer->canFold($source, [], null, [], ['slot']))->toBeTrue();
    });

    it('allows when $slot is echoed unescaped', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '<div>{!! $slot !!}</div>';

        expect($analyzer->canFold($source, [], null, [], ['slot']))->toBeTrue();
    });

    it('aborts when $slot is used in @if directive', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@if($slot) <div>{{ $slot }}</div> @endif';

        expect($analyzer->canFold($source, [], null, [], ['slot']))->toBeFalse();
    });

    it('aborts when $slot is used in @isset directive', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@isset($slot) <div>{{ $slot }}</div> @endisset';

        expect($analyzer->canFold($source, [], null, [], ['slot']))->toBeFalse();
    });

    it('aborts when $slot is used in @unless directive', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@unless($slot->isEmpty()) <div>{{ $slot }}</div> @endunless';

        expect($analyzer->canFold($source, [], null, [], ['slot']))->toBeFalse();
    });

    it('aborts when $slot is used in @php block', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@php $content = $slot; @endphp <div>{{ $content }}</div>';

        expect($analyzer->canFold($source, [], null, [], ['slot']))->toBeFalse();
    });

    it('aborts when $slot is used in standard PHP block', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '<?php $content = $slot; ?> <div>{{ $content }}</div>';

        expect($analyzer->canFold($source, [], null, [], ['slot']))->toBeFalse();
    });

    it('aborts when $slot is transformed with function', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '<div>{{ strtoupper($slot) }}</div>';

        expect($analyzer->canFold($source, [], null, [], ['slot']))->toBeFalse();
    });

    it('aborts when $slot has method call', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '<div>{{ $slot->toHtml() }}</div>';

        expect($analyzer->canFold($source, [], null, [], ['slot']))->toBeFalse();
    });

    it('aborts when $slot has null coalesce on left side', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // $slot on LEFT side - being tested, could be replaced by fallback
        $source = '<div>{{ $slot ?? "default" }}</div>';

        expect($analyzer->canFold($source, [], null, [], ['slot']))->toBeFalse();
    });

    it('allows $slot on right side of null coalesce', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // $slot on RIGHT side - used as fallback, passes through unchanged
        // Common pattern: {{ $message ?? $slot }}
        $source = '<div>{{ $message ?? $slot }}</div>';

        expect($analyzer->canFold($source, [], null, [], ['slot']))->toBeTrue();
    });

    it('allows $slot on right side of null coalesce with prop', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // Real-world pattern: prop with slot fallback
        $source = '@props(["message" => null]) <div>{{ $message ?? $slot }}</div>';

        expect($analyzer->canFold($source, [], null, [], ['slot']))->toBeTrue();
    });

    it('allows when $slot is concatenated', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '<div>{{ $slot . " suffix" }}</div>';

        expect($analyzer->canFold($source, [], null, [], ['slot']))->toBeTrue();
    });

    it('handles named slots the same as default slot', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@if($header) <h1>{{ $header }}</h1> @endif <div>{{ $slot }}</div>';

        // header in @if is unsafe
        expect($analyzer->canFold($source, [], null, [], ['header', 'slot']))->toBeFalse();
    });

    it('allows named slot when only echoed', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '<header>{{ $header }}</header><main>{{ $slot }}</main><footer>{{ $footer }}</footer>';

        expect($analyzer->canFold($source, [], null, [], ['slot', 'header', 'footer']))->toBeTrue();
    });

    it('aborts when one of multiple slots is used unsafely', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '<header>{{ $header }}</header> @if($footer) <footer>{{ $footer }}</footer> @endif';

        // footer is used in @if
        expect($analyzer->canFold($source, [], null, [], ['header', 'footer']))->toBeFalse();
    });

    it('slot overrides prop with same name', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // Even though 'header' has a default in @props, if slot is passed it's dynamic
        $source = '@props(["header" => "Default"]) @if($header) <h1>{{ $header }}</h1> @endif';

        // header is passed as slot, so it's dynamic and @if usage is unsafe
        expect($analyzer->canFold($source, [], null, [], ['header']))->toBeFalse();
    });

    it('allows when slot is passed to child component simply', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '<x-child>{{ $slot }}</x-child>';

        expect($analyzer->canFold($source, [], null, [], ['slot']))->toBeTrue();
    });

    it('aborts when slot is transformed before passing to child', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '<x-child>{{ trim($slot) }}</x-child>';

        expect($analyzer->canFold($source, [], null, [], ['slot']))->toBeFalse();
    });

    it('allows when slot and props are both used safely', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["title"]) <h1>{{ $title }}</h1><div>{{ $slot }}</div>';

        expect($analyzer->canFold($source, ['title'], null, [], ['slot']))->toBeTrue();
    });

    it('aborts when slot is safe but prop is unsafe', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["title"]) @if($title) <h1>{{ $title }}</h1> @endif <div>{{ $slot }}</div>';

        expect($analyzer->canFold($source, ['title'], null, [], ['slot']))->toBeFalse();
    });

    it('aborts when prop is safe but slot is unsafe', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@props(["title"]) <h1>{{ $title }}</h1> @if($slot) <div>{{ $slot }}</div> @endif';

        expect($analyzer->canFold($source, ['title'], null, [], ['slot']))->toBeFalse();
    });

    it('does not double-check variable if in both dynamicAttributes and dynamicSlots', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // If somehow 'header' is in both (edge case), it should still work
        $source = '@props(["header"]) <h1>{{ $header }}</h1>';

        // header appears in both - should not cause issues
        expect($analyzer->canFold($source, ['header'], null, [], ['header']))->toBeTrue();
    });

    it('handles $slot isEmpty method call', function () {
        $analyzer = new DynamicUsageAnalyzer();

        // Common pattern: $slot->isEmpty() - this transforms the slot
        $source = '@if($slot->isEmpty()) <div>No content</div> @else {{ $slot }} @endif';

        expect($analyzer->canFold($source, [], null, [], ['slot']))->toBeFalse();
    });

    it('handles $slot isNotEmpty method call', function () {
        $analyzer = new DynamicUsageAnalyzer();

        $source = '@if($slot->isNotEmpty()) {{ $slot }} @endif';

        expect($analyzer->canFold($source, [], null, [], ['slot']))->toBeFalse();
    });
});
