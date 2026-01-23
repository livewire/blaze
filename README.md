# Blaze

Speed up your Laravel app by optimizing Blade component rendering performance.

```
Rendering 25,000 anonymous components:

Without Blaze  ████████████████████████████████████████  450ms
With Blaze     █                                          12ms
```

## Introduction

Blaze is a Laravel package that eliminates the overhead of Blade's component rendering pipeline. Rendering large numbers of components can be slow. Standard Blade components can be slow when rendering large numbers of them. Blaze compiles components into direct PHP function calls or static HTML, removing this overhead entirely.

## Installation

You can install the package via composer:

```bash
composer require livewire/blaze
```

## Usage

To optimize a Blade component for performance, add the `@blaze` directive at the top of your component file:

```blade
{{-- resources/views/components/button.blade.php --}}

@blaze

@props(['variant' => 'primary'])

<button type="button" class="btn btn-{{ $variant }}">
    {{ $slot }}
</button>
```

> **Using Flux?** All eligible Flux components are already marked with `@blaze` - you don't need to do anything! Just install Blaze and enjoy the performance boost.

### Optimization Strategies

**Available strategies:**

1. **Function compilation** (default) - The most reliable optimization
   - Compiles component into optimized PHP function
   - Removes up to 97% of Blade's component overhead
   - Drop-in solution with zero setup

2. **Compile-time folding** (`fold: true`) - The fastest optimization
   - Pre-renders component at compile-time into static HTML
   - Requires understanding of what makes a component foldable
   - Removes virtually all overhead (~100%)
   - Falls back to function compilation when needed

3. **Runtime memoization** (`memo: true`) - Caches rendered output
   - Caches output based on component name and props
   - Useful for self-closing components rendered multiple times with same props

### Which strategy should I use?

**For most components, use the default:**
```blade
@blaze
```

This removes most of Blade's component overhead with zero concerns about caching or stale data.

**Use memoization for repeated self-closing components:**

Components like icons or avatars benefit from memoization - they typically have a small set of possible values and appear many times on a single page.

**Use folding for maximum performance:**

If you need every last bit of performance and are willing to understand the folding model:

```blade
@blaze(fold: true)
```

See the [Compile-time folding](#compile-time-folding) section for details.

### Configuration

Configure Blaze in your `AppServiceProvider` to enable optimization for entire folders:

```php
use Livewire\Blaze\Blaze;

public function boot(): void
{
    Blaze::compile()
        ->in(resource_path('views/components'))
        ->in(resource_path('views/components/legacy'), composer: true, share: true)
        ->in(resource_path('views/components/icons'), memoize: true)
        ->in(resource_path('views/components/ui'), fold: true)
        ->in(resource_path('views/components/dynamic'), compile: false);
}
```

**Available options:**

| Option | Default | Description |
|--------|---------|-------------|
| `compile` | `true` | Enable Blaze compilation. Set to `false` to exclude a folder. |
| `fold` | `false` | Enable compile-time folding for all components in folder |
| `memoize` | `false` | Enable runtime memoization for all components in folder |
| `composer` | `false` | Enable view composers (disabled by default for performance) |
| `share` | `false` | Enable `View::share()` variables |

Components with explicit `@blaze` directives override folder-level settings.

## Table of contents

- [Function compiler](#function-compiler)
- [Memoization](#memoization)
- [Compile-time folding](#compile-time-folding)
- [The @unblaze directive](#the-unblaze-directive)
- [Performance expectations](#performance-expectations)

## Function compiler

The function compiler transforms your Blade components into optimized PHP functions, removing 94-97% of the rendering overhead.

### How it works

When you add `@blaze` to a component, Blaze compiles it into a direct function call:

```php
// Your component becomes a function
function _c4f8e2a1b3d5f6e7($__blaze, $__data, $__slots, $__bound) {
    $__env = $__blaze->env;
    extract($__slots, EXTR_SKIP);
    
    // Props extraction
    $variant = $__data['variant'] ?? 'primary';
    unset($__data['variant']);
    $attributes = new BlazeAttributeBag($__data);
    
    ?><button type="button" class="btn btn-<?php echo e($variant); ?>">
    <?php echo e($slot); ?>
</button><?php
}

// Called directly - no component resolution overhead
_c4f8e2a1b3d5f6e7($__blaze, ['variant' => 'primary'], ['default' => fn() => 'Save'], []);
```

### Supported features

Blaze focuses on anonymous components and makes trade-offs for performance:

| Feature | Status | Notes |
|---------|--------|-------|
| Anonymous components | Supported | |
| `@props` directive | Supported | With defaults and required props |
| `@aware` directive | Supported | Within Blaze components only |
| Slots (default & named) | Supported | Including slot attributes |
| `$attributes` bag | Supported | merge, class, style, etc. |
| Dynamic attributes | Supported | `:href`, `:class`, etc. |
| Nested components | Supported | |
| Class-based components | Not supported | Use anonymous components |
| `<x-dynamic-component>` | Falls back to Blade | Cannot be optimized |
| Dynamic slot names | Falls back to Blade | `<x-slot :name="$var">` |
| `@aware` across boundaries | Not supported | Parent and child must both use `@blaze` |
| View composers | Opt-in | Enable with `composer: true` |
| `View::share()` | Opt-in | Enable with `share: true` |

### Benchmark results (25,000 components)

| Scenario | Blade Overhead | With Blaze | Reduction |
|----------|----------------|------------|-----------|
| No attributes | 500ms | 13ms | 97.4% |
| Attributes only | 457ms | 26ms | 94.3% |
| Attributes + merge() | 546ms | 44ms | 91.9% |
| Props + attributes | 780ms | 40ms | 94.9% |
| Default slot | 460ms | 22ms | 95.1% |
| Named slots | 696ms | 49ms | 93.0% |
| @aware (nested) | 1,787ms | 129ms | 92.8% |

## Memoization

Runtime memoization caches the rendered output of self-closing components. When the same component is rendered multiple times with identical props, it only executes once.

```blade
{{-- components/icon.blade.php --}}
@blaze(memo: true)

@props(['name', 'size' => 'md'])

<x-dynamic-component :component="'icons.' . $name" :size="$size" />
```

The cache key is based on actual prop values at runtime. If `<x-icon name="check" />` appears 50 times on a page, it only renders once - the rest return cached HTML.

Good candidates for memoization are icons and avatars - they have a small set of possible values and appear many times on a single page.

> **Note:** Memoization only works with self-closing components (no slots).

## Compile-time folding

Blaze supports **compile-time folding** with `@blaze(fold: true)` - pre-rendering components during compilation to remove virtually all overhead.

### How folding works

When a component is folded, Blaze renders it at compile-time and embeds the HTML directly:

```blade
{{-- Your template --}}
<x-badge variant="success">Active</x-badge>

{{-- After folding - just HTML --}}
<span class="badge badge-success">Active</span>
```

The component no longer exists at runtime.

---

### A simple example

```blade
{{-- components/badge.blade.php --}}
@blaze(fold: true)

@props(['variant' => 'primary'])

<span class="badge badge-{{ $variant }}">{{ $slot }}</span>
```

Called with static values, this folds perfectly. But what happens with dynamic values?

---

### How Blaze handles dynamic attributes

Consider this component without `@props`:

```blade
{{-- components/box.blade.php --}}
@blaze(fold: true)

<div {{ $attributes }}>{{ $slot }}</div>
```

When called with a dynamic attribute:

```blade
<x-box :class="$highlighted ? 'bg-yellow' : 'bg-white'">Content</x-box>
```

Blaze uses **placeholder replacement**:

```blade
{{-- Step 1: Replace dynamic value with placeholder --}}
<x-box class="__BLAZE_PH_1__">Content</x-box>
```

```blade
{{-- Step 2: Render the component --}}
<div class="__BLAZE_PH_1__">Content</div>
```

```php
{{-- Step 3: Replace placeholder with original PHP --}}
<div class="<?php echo e($highlighted ? 'bg-yellow' : 'bg-white'); ?>">Content</div>
```

This works because the value passes through `$attributes` unchanged.

---

### When dynamic props break folding

Problems occur when a prop is used in **logic**:

```blade
{{-- components/status-badge.blade.php --}}
@blaze(fold: true)

@props(['status'])

@php
$color = match($status) {
    'active' => 'green',
    'pending' => 'yellow',
    default => 'gray',
};
@endphp

<span class="badge bg-{{ $color }}">{{ $status }}</span>
```

Called with a dynamic prop:

```blade
<x-status-badge :status="$user->status" />
```

What goes wrong:

```blade
{{-- Step 1: Replace with placeholder --}}
<x-status-badge status="__BLAZE_PH_1__" />
```

```php
{{-- Step 2: Render - match() runs with placeholder string --}}
$color = match("__BLAZE_PH_1__") {
    'active' => 'green',   // no match
    'pending' => 'yellow', // no match
    default => 'gray',     // HITS DEFAULT!
};
// Result: <span class="badge bg-gray">__BLAZE_PH_1__</span>
```

```html
{{-- Step 3: Replace placeholder --}}
<span class="badge bg-gray"><?php echo e($user->status); ?></span>
```

The color is permanently `gray` because `match()` ran with the placeholder.

**This is why Blaze aborts folding when a defined prop receives a dynamic value.**

---

### The `safe` parameter

If a prop is just passed through without logic, mark it as safe:

```blade
@blaze(fold: true, safe: ['title'])

@props(['title', 'level' => 2])

<h{{ $level }}>{{ $title }}</h{{ $level }}>
```

Now dynamic titles work:

```blade
<x-heading :title="$post->title" />  {{-- Folds successfully --}}
```

**Mark all props as safe:**

```blade
@blaze(fold: true, safe: ['*'])
```

---

### The `unsafe` parameter

Mark normally-safe things as unsafe:

**Attributes used in logic:**

```blade
@blaze(fold: true, unsafe: ['attributes'])

@php
$icon = match($attributes->get('action')) {
    'save' => 'check',
    default => 'arrow-right',
};
@endphp

<button {{ $attributes->except('action') }}>
    <x-icon name="{{ $icon }}" />
</button>
```

**Slots that are inspected:**

```blade
@blaze(fold: true, unsafe: ['slot'])

@php $hasContent = !empty(trim($slot->toHtml())); @endphp

<div @if(!$hasContent) hidden @endif>{{ $slot }}</div>
```

**Everything:**

```blade
@blaze(fold: true, unsafe: ['*'])
```

---

### Global state

Components that read global state cannot be folded:

```blade
@csrf
@auth ... @endauth
$errors->has('email')
session('username')
request()->is('/')
now()
auth()->user()
```

Use `@blaze` (function compilation) or wrap in `@unblaze` blocks.

---

### Folding checklist

- [ ] No global state (`@csrf`, `$errors`, `auth()`, `session()`, `request()`, `now()`)
- [ ] No class-based child components
- [ ] Dynamic props are passed through unchanged OR marked `safe`
- [ ] Dynamic attributes used in logic are marked `unsafe`

## The @unblaze directive

Creates dynamic sections within folded components - for when you need global state in an otherwise foldable component.

### Example

```blade
@blaze(fold: true)

@props(['name', 'label'])

<div>
    <label>{{ $label }}</label>
    <input type="text" name="{{ $name }}">

    @unblaze
        @if($errors->has($name))
            <span>{{ $errors->first($name) }}</span>
        @endif
    @endunblaze
</div>
```

The label and input are folded. The error handling remains dynamic.

### Passing data with scope

```blade
@unblaze(scope: ['name' => $name])
    <x-form.errors :name="$scope['name']" />
@endunblaze
```

### Alternative: Extract to separate component

```blade
{{-- input.blade.php - folded --}}
@blaze(fold: true)

@props(['name', 'label'])

<div>
    <label>{{ $label }}</label>
    <input type="text" name="{{ $name }}">
    <x-input-errors :name="$name" />
</div>
```

```blade
{{-- input-errors.blade.php - not folded --}}
@blaze

@props(['name'])

@error($name) <span>{{ $message }}</span> @enderror
```

## Performance expectations

> **TBD** - This section needs updated benchmarks.

Blaze removes 94-97% of Blade's component rendering overhead. The actual impact depends on how many components you render per page.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
