# 🔥 Blaze

Speed up your Laravel app by optimizing Blade component rendering performance.

```
Rendering 25,000 anonymous components:

Without Blaze  ████████████████████████████████████████  450ms
With Blaze     █                                          12ms
```

## Installation

```bash
composer require livewire/blaze
```

## Introduction

Rendering large numbers of Blade components can be slow due to Laravel's expensive component pipeline. Blaze eliminates this overhead by compiling components into direct PHP function calls or static HTML.

### Limitations

**Blaze only works with anonymous Blade components**, these are often used as building blocks for design systems and UI libraries like [Flux UI](https://fluxui.dev), which Blaze was built for originally.

> [!TIP]
> If you're using Flux, just install Blaze and you're good to go!

You can also benefit from using Blaze in your own projects but be mindful of its limitations:

- **Class-based components** are not supported
- **`View::share()`** variables will not be injected
- **View composers / creators** will not run
- **Component lifecycle events** will not fire

## Usage

Getting started with Blaze takes one line of code.

Add the following to your `AppServiceProvider`:

```php
use Livewire\Blaze\Blaze;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Blaze::optimize();
}
```

That's it.

### Configuration

Due to these limitations, you may want to gradually roll out Blaze by opting in specific folders or components.

**Per-folder:**

```php
Blaze::optimize()
    ->in(resource_path('views/components/icons'))
    ->in(resource_path('views/components/ui'));
```

**Per-component:**

```blade
@blaze

<button>
    {{ $slot }}
</button>
```

## Table of contents

- [Optimization strategies](#optimization-strategies)
- [Function compiler](#function-compiler)
- [Compile-time folding](#compile-time-folding)
- [Memoization](#memoization)
- [Reference](#reference)

## Optimization strategies

Blaze offers three optimization strategies, each suited for different scenarios:

| Strategy | When to use |
|----------|-------------|
| **Function compilation** (default) | For most components - reliable 94-97% overhead reduction with zero concerns about caching or stale data |
| **Compile-time folding** (`fold: true`) | For maximum performance when you understand the folding model and your component's data flow |
| **Runtime memoization** (`memo: true`) | For self-closing components like icons or avatars that appear many times on a page with the same props |

## Function compiler

The function compiler transforms your Blade components into optimized PHP functions, removing 94-97% of the rendering overhead.

### How it works

When you enable Blaze, it compiles your components into direct function calls:

```php
{{-- components/button.blade.php becomes: --}}

function _c4f8e2a1($__blaze, $__data, $__slots, $__bound) {
    $variant = $__data['variant'] ?? 'primary';
    $attributes = new BlazeAttributeBag($__data);
    ?>
    <button class="btn-<?php echo e($variant); ?>">
        <?php echo e($slot); ?>
    </button>
    <?php
}

{{-- <x-button variant="primary">Save</x-button> compiles to: --}}

_c4f8e2a1($__blaze, ['variant' => 'primary'], ['default' => fn() => 'Save'], []);
```

Instead of resolving the component through Blade's rendering pipeline, Blaze calls the function directly.

### Feature support

Blaze supports all essential features of anonymous components including `@props`, `@aware`, `$attributes`, slots, and dynamic attributes.

> **Note:** When using `@aware`, both the parent and child components must use `@blaze` for the values to propagate correctly.

### Benchmark results

The benchmarks below demonstrate the Blade overhead that Blaze eliminates when rendering 25,000 components:

| Scenario | Blade Overhead | With Blaze | Reduction |
|----------|----------------|------------|-----------|
| No attributes | 500ms | 13ms | 97.4% |
| Attributes only | 457ms | 26ms | 94.3% |
| Attributes + merge() | 546ms | 44ms | 91.9% |
| Props + attributes | 780ms | 40ms | 94.9% |
| Default slot | 460ms | 22ms | 95.1% |
| Named slots | 696ms | 49ms | 93.0% |
| @aware (nested) | 1,787ms | 129ms | 92.8% |

These numbers reflect the rendering pipeline overhead, not the work your components do internally. If your components perform expensive operations (database queries, API calls, complex calculations), that work will still affect performance.

If your application is still slow after installing Blaze, first investigate other bottlenecks in your application. If you determine that slowness is still coming from Blade rendering, consider [compile-time folding](#compile-time-folding).

## Compile-time folding

Compile-time folding is Blaze's most powerful optimization strategy, pre-rendering components during compilation to remove virtually all overhead. However, it requires a deep understanding of how folding works. **Used incorrectly, folding can cause subtle bugs that are difficult to diagnose.** Read this section carefully before enabling it.

```blade
@blaze(fold: true)
```

### How folding works

When a component is folded, Blaze renders it at compile-time and embeds the HTML directly into your template:

```blade
{{-- Your components --}}

{{-- components/badge.blade.php --}}
@blaze(fold: true)

@props(['variant' => 'primary'])

<span class="inline-flex items-center rounded-full px-2 py-1 text-xs
    {{ $variant === 'success' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">
    {{ $slot }}
</span>

{{-- components/box.blade.php --}}
@blaze(fold: true)

<div {{ $attributes->class(['p-4 rounded-lg']) }}>
    {{ $slot }}
</div>
```

```blade
{{-- Your template --}}
<x-badge variant="success">Active</x-badge>
<x-box class="bg-white shadow">Content</x-box>

{{-- After folding - just HTML, no component overhead --}}
<span class="inline-flex items-center rounded-full px-2 py-1 text-xs bg-green-100 text-green-700">Active</span>
<div class="p-4 rounded-lg bg-white shadow">Content</div>
```

The component no longer exists at runtime.

---

### Dynamic attributes

This is where things get more complicated. Blaze can handle dynamic attributes, but only when they **pass through unchanged** via `$attributes`.

Consider a component that doesn't define any props - all attributes are just passed through:

```blade
{{-- components/card.blade.php --}}
@blaze(fold: true)

<div {{ $attributes->class(['rounded-lg shadow-md p-6']) }}>
    {{ $slot }}
</div>
```

When called with a dynamic attribute:

```blade
<x-card :class="$featured ? 'bg-yellow-50' : 'bg-white'">
    Featured content
</x-card>
```

Blaze uses **placeholder replacement** to fold this correctly:

```blade
{{-- Step 1: Replace dynamic value with placeholder --}}
<x-card class="__BLAZE_PH_1__">Featured content</x-card>

{{-- Step 2: Render the component at compile-time --}}
<div class="rounded-lg shadow-md p-6 __BLAZE_PH_1__">Featured content</div>

{{-- Step 3: Replace placeholder with original PHP expression --}}
<div class="rounded-lg shadow-md p-6 <?php echo e($featured ? 'bg-yellow-50' : 'bg-white'); ?>">Featured content</div>
```

This works because the value passes through `$attributes` unchanged - Blaze doesn't need to understand what the value is, just where it ends up.

---

### @props

Problems occur when a dynamic value is captured by `@props` and used in logic or transformations:

```blade
{{-- components/status-badge.blade.php --}}
@blaze(fold: true)

@props(['status'])

@php
    $colors = [
        'active' => 'bg-green-100 text-green-800',
        'pending' => 'bg-yellow-100 text-yellow-800',
        'inactive' => 'bg-gray-100 text-gray-800',
    ];
@endphp

<span class="px-2 py-1 rounded-full text-sm {{ $colors[$status] ?? $colors['inactive'] }}">
    {{ $status }}
</span>
```

When called with a **dynamic** prop:

```blade
<x-status-badge :status="$user->status" />
```

Here's what goes wrong:

| Step | What happens |
|------|--------------|
| **1. Placeholder** | `<x-status-badge status="__BLAZE_PH_1__" />` |
| **2. Render** | `$colors["__BLAZE_PH_1__"]` fails lookup, falls back to `inactive` |
| **3. Result** | `<span class="... bg-gray-100 text-gray-800">__BLAZE_PH_1__</span>` |

The color is permanently `inactive` because the array lookup ran with the placeholder string, not the actual value.

**This is why Blaze aborts folding when a defined prop receives a dynamic value.**

---

### The `safe` parameter

If a prop is only passed through to the output and never used in conditions, transformations (like `Str::title($prop)`), or lookups, you can mark it as `safe`:

```blade
@blaze(fold: true, safe: ['title'])

@props(['title', 'level' => 2])

<h{{ $level }} class="font-bold text-gray-900">
    {{ $title }}
</h{{ $level }}>
```

Now dynamic titles fold successfully:

```blade
<x-heading :title="$post->title" />  {{-- Folds --}}
```

> **Warning:** Only mark props as safe if they truly pass through unchanged. If you use a "safe" prop in a condition like `@if($title)` or transform it like `{{ Str::upper($title) }}`, the condition/transformation will be evaluated at compile-time with a placeholder value, causing bugs.

**Mark all props as safe:**

```blade
@blaze(fold: true, safe: ['*'])
```

---

### The `unsafe` parameter

Sometimes you need to prevent folding when certain dynamic values are present.

**When any dynamic attribute would break the component:**

```blade
@blaze(fold: true, unsafe: ['attributes'])

@php
    $icon = match($attributes->get('variant')) {
        'success' => 'check-circle',
        'error' => 'x-circle',
        default => 'info-circle',
    };
@endphp

<div {{ $attributes->except('variant') }}>
    <x-icon :name="$icon" />
    {{ $slot }}
</div>
```

With `unsafe: ['attributes']`, if **any** dynamic attribute is passed, folding aborts and the component falls back to function compilation.

**When a specific attribute breaks the component:**

If only certain attributes are used in logic, you can be more precise:

```blade
@blaze(fold: true, unsafe: ['attributes.variant'])

@php
    $icon = match($attributes->get('variant')) {
        'success' => 'check-circle',
        default => 'info-circle',
    };
@endphp

<div {{ $attributes->except('variant') }}>
    <x-icon :name="$icon" />
    {{ $slot }}
</div>
```

Now `<x-alert :variant="$type">` aborts folding, but `<x-alert :class="$classes">` still folds.

---

### Slots

All slots are considered **safe by default** - their content passes through to the output unchanged.

However, this means slot inspection methods won't work as expected:

```blade
{{-- This won't work correctly when folded --}}
@if($slot->isNotEmpty())
    <div class="content">{{ $slot }}</div>
@endif
```

Because slots are replaced with placeholders during folding, `$slot->isNotEmpty()` will always return `true` (the placeholder is not empty).

If you need to inspect slot content, mark the slot as unsafe:

```blade
@blaze(fold: true, unsafe: ['slot'])

@if($slot->isNotEmpty())
    <div class="content">{{ $slot }}</div>
@endif
```

For named slots:

```blade
@blaze(fold: true, unsafe: ['footer'])

@if($footer->isNotEmpty())
    <footer>{{ $footer }}</footer>
@endif
```

---

### Global state

When components are folded, they are rendered at compile-time. This means any global state is captured at the moment of compilation, not at runtime.

Consider this component:

```blade
@blaze(fold: true)

@if(auth()->check())
    <span>Welcome, {{ auth()->user()->name }}</span>
@else
    <span>Please log in</span>
@endif
```

If a logged-in user triggers the compilation (by being the first to visit the page after a cache clear), the "Welcome" message gets baked into the template. **All subsequent users - including logged-out users - will see the same content.**

> **Warning:** Components that read global state should never be folded. Use `@blaze` (function compilation) instead, or wrap the dynamic section in `@unblaze`.

**Common global state to watch for:**

```blade
auth()->check(), auth()->user()     {{-- Authentication --}}
session('key'), session()->get()    {{-- Session data --}}
request()->path(), request()->is()  {{-- Request data --}}
$errors->has(), $errors->first()    {{-- Validation errors --}}
now(), Carbon::now()                {{-- Current time --}}
@csrf                               {{-- CSRF tokens --}}
@auth, @guest                       {{-- Auth directives --}}
```

---

### The @unblaze directive

When you have a component that's mostly static but needs one dynamic section, use `@unblaze` to exclude that section from folding:

```blade
@blaze(fold: true)

@props(['name', 'label'])

<div class="form-group">
    <label class="block text-sm font-medium text-gray-700">{{ $label }}</label>
    <input type="text" name="{{ $name }}" class="mt-1 block w-full rounded-md border-gray-300">

    @unblaze
        @error($name)
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    @endunblaze
</div>
```

The label and input are folded (static). The error handling remains dynamic and executes at runtime.

**Passing data into @unblaze:**

Variables from the component scope aren't automatically available inside `@unblaze`. Use the `scope` parameter:

```blade
@unblaze(scope: ['name' => $name])
    @error($scope['name'])
        <p class="text-red-600">{{ $message }}</p>
    @enderror
@endunblaze
```

---

### Folding checklist

Before enabling `fold: true` on a component, verify:

- [ ] **No global state** - No `auth()`, `session()`, `request()`, `$errors`, `now()`, `@csrf`, `@auth`/`@guest`
- [ ] **No class-based child components** - All nested `<x-*>` components must be anonymous
- [ ] **Props are pass-through OR marked safe** - If a prop is used in conditions/transformations, don't mark it safe
- [ ] **Attributes used in logic are marked unsafe** - If you call `$attributes->get()` in conditions, mark `attributes` or the specific attribute as unsafe
- [ ] **Slots used in conditions are marked unsafe** - If you check `$slot->isNotEmpty()`, mark that slot as unsafe
- [ ] **No `View::share()` or view composers** - Unless you've enabled them with `share: true` or `composer: true`

**When in doubt, use `@blaze` without folding.** Function compilation provides excellent performance (94-97% overhead reduction) with none of the footguns.

## Memoization

Runtime memoization is an optimization for specific component types like icons and avatars. These components are dynamic in nature (they can't be folded because their output depends on props), but they're often rendered many times on a page with the same values.

```blade
{{-- components/icon.blade.php --}}
@blaze(memo: true)

@props(['name', 'class' => ''])

<svg class="w-5 h-5 {{ $class }}">
    <use href="/icons.svg#{{ $name }}" />
</svg>
```

The cache key is based on actual prop values at runtime. If `<x-icon name="check" />` appears 50 times on a page, it only renders once - the rest return cached HTML.

> **Note:** Memoization only works with self-closing components (components without slots).

## Reference

### Directive parameters

Use these parameters with the `@blaze` directive:

```blade
@blaze(fold: true, safe: ['title'], unsafe: ['attributes'])
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `fold` | `bool` | `false` | Enable compile-time folding |
| `memo` | `bool` | `false` | Enable runtime memoization |
| `safe` | `array` | `[]` | Props that pass through unchanged and can be folded even when dynamic |
| `unsafe` | `array` | `[]` | Props, attributes, or slots that should abort folding when dynamic |

**Special values for `safe` and `unsafe`:**

- `'*'` - All props
- `'attributes'` - The entire `$attributes` bag
- `'attributes.name'` - A specific attribute
- `'slot'` - The default slot
- `'slotName'` - A named slot

### Folder configuration

Configure folders in your `AppServiceProvider`:

```php
Blaze::optimize()
    ->in(resource_path('views/components/ui'), fold: true)
    ->in(resource_path('views/components/icons'), memoize: true)
    ->in(resource_path('views/components/legacy'), composer: true, share: true)
    ->in(resource_path('views/components/dynamic'), compile: false);
```

| Option | Default | Description |
|--------|---------|-------------|
| `compile` | `true` | Enable Blaze for this folder. Set to `false` to exclude. |
| `fold` | `false` | Enable compile-time folding for all components in folder |
| `memoize` | `false` | Enable runtime memoization for all components in folder |
| `composer` | `false` | Enable view composers (disabled by default for performance) |
| `share` | `false` | Enable `View::share()` variables |
| `events` | `false` | Enable component lifecycle events |

Components with explicit `@blaze` directives override folder-level settings.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
