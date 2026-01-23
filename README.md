# 🔥 Blaze

Eliminate Blade component overhead in your Laravel app.

> **New blazingly fast function compiler!** Drop-in replacement for Blade components with full feature parity. Removes 94-97% of Blade's component rendering overhead. More reliable, no caching concerns, works everywhere.

```
Blade component overhead (25,000 renders):

Without Blaze  ████████████████████████████████████████  450ms
With Blaze     █                                          12ms

                                              97% overhead eliminated
```

## Introduction

Blaze is a Laravel package that eliminates the overhead of Blade's component rendering pipeline. Standard Blade components go through class instantiation, attribute bag construction, view resolution, and multiple compilation passes on every render. Blaze compiles components into direct PHP function calls, removing this overhead entirely.

**Key benefits:**
- **94-97% less overhead** - eliminates Blade's component rendering pipeline
- **Drop-in replacement** - works with existing components
- **Compile-time optimization** - no runtime cost
- **Reliable** - no caching issues or stale data concerns

## Installation

You can install the package via composer:

```bash
composer require livewire/blaze:^1.1@beta
```

## Usage

To optimize a Blade component for performance, simply add the `@blaze` directive at the top of your component file:

```blade
{{-- resources/views/components/button.blade.php --}}

@blaze

@props(['variant' => 'primary'])

<button type="button" class="btn btn-{{ $variant }}">
    {{ $slot }}
</button>
```

> **Using Flux?** All eligible Flux components are already marked with `@blaze` - you don't need to do anything! Just install Blaze and enjoy the performance boost.

When you use this component in your templates:

```blade
<x-button variant="secondary">
    Save
</x-button>
```

Blaze compiles it into a direct function call, bypassing Laravel's component resolution overhead:

```php
<?php echo _abc123($__blaze, ['variant' => 'secondary'], ['default' => 'Save']); ?>
```

### Optimization Strategies

The `@blaze` directive supports optional parameters to control different optimization strategies:

```blade
{{-- Function compilation (default) - removes 94-97% of overhead --}}
@blaze

{{-- Compile-time folding - removes ~100% of overhead but only works with static usage --}}
@blaze(fold: true)

{{-- Runtime memoization - caches component output --}}
@blaze(memo: true)
```

**Available strategies:**

1. **Function compilation** (default) - The most reliable optimization
   - Compiles component into optimized PHP function
   - Works with any props (static or dynamic)
   - Removes 94-97% of Blade's component overhead
   - No caching concerns or stale data

2. **Compile-time folding** (`fold: true`) - The fastest but most restrictive
   - Pre-renders component at compile-time
   - Only works when props and slots are static values
   - Removes virtually all overhead (~100%) - component becomes static HTML
   - Automatically falls back to function compilation if dynamic values are detected

3. **Runtime memoization** (`memo: true`) - Caches rendered output
   - Caches output based on component name and props
   - Reduces re-rendering of identical components
   - Only useful for self-closing components
   - Complements other strategies

**Parameters:**
- `fold: true/false` - Enable compile-time folding (default: false)
- `memo: true/false` - Enable runtime memoization (default: false)

### Which strategy should I use?

**For 99% of components, use the default:**
```blade
@blaze
```

This removes 94-97% of Blade's component overhead with zero concerns about caching or stale data.

**Use folding only if:**
- Component is always called with static props (rare)
- You need every last microsecond of performance
- You understand the folding requirements

```blade
@blaze(fold: true)
```

## Table of contents

- [New function compiler](#new-function-compiler)
- [When to use @blaze](#when-to-use-blaze)
- [Advanced: Compile-time folding](#advanced-compile-time-folding)
- [Making impure components foldable with @unblaze](#making-impure-components-foldable-with-unblaze)
- [Performance expectations](#performance-expectations)
- [Debugging](#debugging)

## New function compiler

Blaze includes a blazingly fast function compiler that transforms your Blade components into optimized PHP functions. This compiler has **full feature parity** with Laravel's Blade components while removing **94-97% of the rendering overhead**.

### How it works

When you add `@blaze` to a component, Blaze compiles it into a direct function call instead of going through Laravel's component resolution and rendering pipeline:

```blade
{{-- Standard Blade component --}}
<x-button variant="primary">Save</x-button>

{{-- Compiled by Blaze into --}}
<?php echo _abc123($__blaze, ['variant' => 'primary'], ['default' => 'Save']); ?>
```

This eliminates the overhead of:
- Component class instantiation
- Attribute bag construction
- View factory resolution
- Multiple compilation passes

### Feature parity

The function compiler supports **all Blade component features**:
- `@props` with defaults and required props
- `@aware` for passing data down component trees
- Default and named slots (`$slot`, `<x-slot>`)
- `$attributes` bag with merge, class, style methods
- Kebab-case to camelCase prop conversion
- Dynamic attributes (`:href`, `:class`, etc.)
- Slot attributes
- Nested components

### Benchmark results (25,000 components)

These benchmarks measure the time spent in Blade's component rendering pipeline - the overhead that Blaze eliminates:

| Scenario | Blade Overhead | With Blaze | Reduction |
|----------|----------------|------------|-----------|
| No attributes | 500ms | 13ms | 97.4% |
| Attributes only | 457ms | 26ms | 94.3% |
| Attributes + merge() | 546ms | 44ms | 91.9% |
| Attributes + class() | 720ms | 46ms | 93.5% |
| Props + attributes | 780ms | 40ms | 94.9% |
| Default slot | 460ms | 22ms | 95.1% |
| Named slots | 696ms | 49ms | 93.0% |
| @aware (nested) | 1,787ms | 129ms | 92.8% |

### Why function compilation?

**Reliability**: Works with both static and dynamic props - no special cases or fallbacks

**Performance**: Consistently removes 94-97% of overhead across all scenarios

**Simplicity**: Drop-in replacement - just add `@blaze` to your components

**No caching concerns**: No stale data, no cache invalidation complexity

**Full compatibility**: Supports every Blade component feature you're already using

### Limitations

While Blaze supports all standard Blade component features, there are a few advanced Laravel features that are not available:

**Not supported:**
- View composers - Components with view composers attached will need to pass data through props instead
- Custom `View::share()` variables - Shared variables are not automatically injected (use `$__env->shared('key')` to access them)
- Dynamic component resolution (`<x-dynamic-component>`) - These fall back to standard Blade rendering
- Dynamic slot names (`<x-slot :name="$variable">`) - These fall back to standard Blade rendering
- Class-based components - Only anonymous components are supported
- `@aware` across Blade/Blaze boundaries - `@aware` only works when both parent and child use `@blaze`
- `Blade::stringable()` - Custom stringable callbacks are not invoked

## When to use @blaze

**Simple answer: Use `@blaze` on any component!**

With the function compiler, `@blaze` can be safely added to virtually any Blade component. It's a drop-in replacement that works with:

- Static components (buttons, cards, badges)
- Dynamic components (user profiles, data tables)
- Components with `@props`
- Components with `$attributes`
- Components with slots
- Components with `@aware`
- Nested components
- Components that accept dynamic data through props

### Optional: Runtime Memoization

If you have self-closing components that are rendered repeatedly with the same props, you can enable memoization:

```blade
{{-- Enable memoization for repeated renders --}}

@blaze(memo: true)

@props(['userId'])

<div class="user-avatar">
    {{-- Component content --}}
</div>
```

This caches the rendered output, so if you render `<x-user-avatar :user-id="5" />` multiple times on the same page, it only renders once.

**When to use memoization:**
- Self-closing components only (has no slots)
- Component is rendered multiple times with identical props
- Component has expensive rendering logic

**Note:** Most components don't need memoization - function compilation is already very fast.

## Advanced: Compile-time folding

> **Most users can skip this section.** The default function compilation works for 99% of use cases. Folding is an advanced optimization with strict requirements.

Blaze also supports **compile-time folding** with `@blaze(fold: true)` - an extreme optimization that pre-renders components during compilation, removing virtually all component overhead. However, folding only works under specific conditions.

### How folding works

When a component is folded, Blaze renders it at compile-time and embeds the resulting HTML directly into the compiled view. At runtime, there's zero component overhead - the HTML is already there, as if you'd written it by hand.

```blade
{{-- This component... --}}
<x-badge variant="success">Active</x-badge>

{{-- ...becomes this HTML at compile time --}}
<span class="badge badge-success">Active</span>
```

This is why folding removes virtually 100% of the overhead - the component no longer exists at runtime.

### When to use folding

Use `@blaze(fold: true)` only if:
1. Component is called with static prop and slot values
2. Component has no runtime dependencies (no `@csrf`, `$errors`, `auth()`, etc.)
3. You need the absolute maximum performance

```blade
{{-- Example: This can be folded --}}
@blaze(fold: true)

@props(['variant' => 'primary'])

<button class="btn-{{ $variant }}">{{ $slot }}</button>

{{-- Called with static values --}}
<x-button variant="secondary">Save</x-button>
```

### Dynamic values and folding

The key concept to understand is that folding happens at **compile-time**, not runtime. Any value that could change between requests will cause incorrect results if folded.

**Static values** - known at compile time:
```blade
<x-button variant="primary">Save</x-button>
<x-card title="Welcome">...</x-card>
```

**Dynamic values** - only known at runtime:
```blade
<x-button :variant="$userRole">Save</x-button>
<x-card :title="$post->title">...</x-card>
<x-alert>{{ $message }}</x-alert>
```

When Blaze detects dynamic values being passed to a foldable component, it must decide whether to proceed with folding or abort. This is controlled by the `safe` and `unsafe` parameters.

### What's safe by default

**Dynamic attributes** - Attributes that are NOT captured as `@props` are safe by default. They pass through the `$attributes` bag unchanged:

```blade
@blaze(fold: true)
@props(['variant' => 'primary'])

{{-- $attributes passes through anything not in @props --}}
<button class="btn-{{ $variant }}" {{ $attributes }}>
    {{ $slot }}
</button>
```

```blade
{{-- This works - :disabled is not a prop, it passes through $attributes --}}
<x-button variant="primary" :disabled="$isLoading">Save</x-button>
```

### What's NOT safe by default

**Defined props** - Any prop declared in `@props([...])` is NOT safe by default. This is because when you capture a prop, you likely use it for:
- Conditions: `@if($variant === 'danger')`
- Transformations: `{{ Str::upper($title) }}`
- Derived values: `$classes = $large ? 'text-lg' : 'text-sm'`

All of these would produce incorrect results if folded with a dynamic value.

If a prop is dynamic and NOT in the `safe` list, folding aborts and falls back to function compilation.

**Slots** - Slots are also NOT safe by default because their content could contain dynamic expressions, directives, or other components that must be evaluated at runtime.

### The `safe` parameter

If you have a prop that's just passed through without transformation, you can mark it as safe:

```blade
@blaze(fold: true, safe: ['title'])
@props(['title', 'variant' => 'primary'])

<div class="card card-{{ $variant }}">
    <h1>{{ $title }}</h1>  {{-- title is just passed through --}}
    {{ $slot }}
</div>
```

Now this works:
```blade
{{-- Folding proceeds - title is marked as safe --}}
<x-card :title="$post->title" variant="featured">
    <p>Static content here</p>
</x-card>
```

**The `safe: ['*']` wildcard:**

If all your props are just passed through (no conditions, no transformations), you can mark everything as safe:

```blade
@blaze(fold: true, safe: ['*'])
@props(['title', 'subtitle', 'icon'])

<div class="header">
    <x-icon name="{{ $icon }}" />
    <h1>{{ $title }}</h1>
    <p>{{ $subtitle }}</p>
</div>
```

### The `unsafe` parameter

The `unsafe` parameter marks things that are normally safe as unsafe, forcing folding to abort if they contain dynamic values.

**Marking attributes as unsafe:**

If your component reads from `$attributes` to derive values, you need to mark attributes as unsafe:

```blade
@blaze(fold: true, unsafe: ['attributes'])

@php
$icon = match($attributes->get('action')) {
    'save' => 'check',
    'delete' => 'trash',
    'edit' => 'pencil',
    default => 'arrow-right',
};
@endphp

<button {{ $attributes->except('action') }}>
    <x-icon name="{{ $icon }}" />
    {{ $slot }}
</button>
```

Here, the `action` attribute is used to derive which icon to display. If `action` is dynamic, folding would bake in the wrong icon. With `unsafe: ['attributes']`, folding aborts when any attribute is dynamic.

**Marking slots as unsafe:**

If your component inspects slot content to make decisions, mark the slot as unsafe:

```blade
@blaze(fold: true, unsafe: ['slot'])
@props(['collapsed' => false])

@php
$hasContent = !empty(trim($slot->toHtml()));
@endphp

<div class="panel" @if(!$hasContent) hidden @endif>
    {{ $slot }}
</div>
```

Here the component checks whether the slot has content. If the slot content is dynamic, the `$hasContent` check would be incorrect after folding.

> **Note:** Since slots are NOT safe by default, this example would already abort folding if the slot contains dynamic content. The `unsafe: ['slot']` is useful when you want to abort folding even for static slot content that you're inspecting programmatically.

**The `unsafe: ['*']` wildcard:**

Forces folding to abort if ANY dynamic value is detected (props, attributes, or slots):

```blade
@blaze(fold: true, unsafe: ['*'])
{{-- Only folds if everything is completely static --}}
```

**Specifying slot names:**

You can mark specific slots as unsafe:

```blade
@blaze(fold: true, unsafe: ['actions'])
@props(['title'])

<div class="card">
    <h1>{{ $title }}</h1>
    {{ $slot }}
    <div class="card-actions">
        @if(isset($actions) && !empty(trim($actions->toHtml())))
            {{ $actions }}
        @endif
    </div>
</div>
```

### The folding litmus test

Ask yourself these questions about your component:

1. **Does it work the same for all users?** (no auth checks, no user-specific content)
2. **Does it work the same on every request?** (no request data, no CSRF tokens)
3. **Does it work the same at any time?** (no timestamps, no "time ago" formatting)
4. **Does it only use the props you pass in?** (no session data, no database queries)
5. **Are all child components it renders also foldable?** (no non-foldable components inside)
6. **Is it always called with static props?** (no `:prop="$variable"` unless marked safe)

**If you answered YES to all questions -> Can use `@blaze(fold: true)`**
**If you answered NO to any question -> Use default `@blaze` instead**

### Cannot fold components with

Avoid `@blaze(fold: true)` for components that have runtime dependencies:

```blade
{{-- CSRF tokens change per request --}}
<form method="POST">
    @csrf <!-- Cannot fold -->
    <button type="submit">Submit</button>
</form>
```

```blade
{{-- Authentication state changes at runtime --}}
@auth <!-- Cannot fold -->
    <p>Welcome back!</p>
@endauth
```

```blade
{{-- Error bags are request-specific --}}
@if($errors->has('email')) <!-- Cannot fold -->
    <span class="error">{{ $errors->first('email') }}</span>
@endif
```

```blade
{{-- Session data changes at runtime --}}
<div>Welcome, {{ session('username') }}</div> <!-- Cannot fold -->
```

```blade
{{-- Request data varies per request --}}
<div @class(['active' => request()->is('/')])>Home</div> <!-- Cannot fold -->
```

```blade
{{-- Time-dependent content --}}
<p>Generated on {{ now() }}</p> <!-- Cannot fold -->
```

For these cases, use the default `@blaze` (function compilation) - you still remove 94-97% of the overhead without the folding restrictions.

## Making impure components foldable with @unblaze

> **Note:** The `@unblaze` directive is primarily useful with **folding** (`@blaze(fold: true)`). With the default function compilation strategy, you typically don't need `@unblaze` - just pass dynamic data through props instead.

Sometimes you have a component that's *mostly* static, but contains a small dynamic section that would normally prevent it from being folded (like `$errors`, `request()`, or `session()`). The `@unblaze` directive lets you "punch a hole" in an otherwise foldable component, keeping the static parts pre-rendered while allowing specific sections to remain dynamic.

### The problem

Imagine a form input component that's perfect for `@blaze` - except it needs to show validation errors:

```blade
{{-- Cannot use @blaze(fold: true) - $errors prevents folding --}}

<div>
    <label>{{ $label }}</label>
    <input type="text" name="{{ $name }}">

    @if($errors->has($name))
        <span>{{ $errors->first($name) }}</span>
    @endif
</div>
```

Without `@unblaze`, you have to choose: either skip folding entirely (losing the near-total overhead elimination), or remove the error handling (losing functionality).

### The solution: @unblaze

The `@unblaze` directive creates a dynamic section within a folded component:

```blade
{{-- Now we can use folding! --}}

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

**What happens:**
- The `<div>`, `<label>`, and `<input>` are folded (pre-rendered at compile time)
- The error handling inside `@unblaze` remains dynamic (evaluated at runtime)
- You get the best of both worlds: folding optimization + dynamic functionality

**Alternative approach (recommended for most cases):**

Instead of using `@unblaze`, extract the dynamic part into a separate component:

```blade
{{-- input.blade.php - can be folded --}}
@blaze(fold: true)

@props(['name', 'label'])

<div>
    <label>{{ $label }}</label>
    <input type="text" name="{{ $name }}">
    <x-input-errors :name="$name" />
</div>
```

```blade
{{-- input-errors.blade.php - stays dynamic --}}
@props(['name'])

@error($name)
    <span>{{ $message }}</span>
@enderror
```

This approach is simpler and more maintainable for most use cases.

### Using scope to pass data into @unblaze

Sometimes you need to pass component props into the `@unblaze` block. Use the `scope` parameter:

```blade
@blaze(fold: true)

@props(['userId', 'showStatus' => true])

<div>
    <h2>User Profile</h2>
    {{-- Lots of static markup here --}}

    @unblaze(scope: ['userId' => $userId, 'showStatus' => $showStatus])
        @if($scope['showStatus'])
            <div>User #{{ $scope['userId'] }} - Last seen: {{ session('last_seen') }}</div>
        @endif
    @endunblaze
</div>
```

**How scope works:**
- Variables captured in `scope:` are encoded into the compiled view
- Inside the `@unblaze` block, access them via `$scope['key']`
- This allows the unblaze section to use component props while keeping the rest folded

### Nested components inside @unblaze

You can render other components inside `@unblaze` blocks, which is useful for extracting reusable dynamic sections:

```blade
@blaze(fold: true)

@props(['name', 'label'])

<div>
    <label>{{ $label }}</label>
    <input type="text" name="{{ $name }}">

    @unblaze(scope: ['name' => $name])
        <x-form.errors :name="$scope['name']" />
    @endunblaze
</div>
```

```blade
{{-- components/form/errors.blade.php --}}

@props(['name'])

@error($name)
    <p>{{ $message }}</p>
@enderror
```

This allows you to keep your error display logic in a separate component while still using it within the unblaze section. The form input remains folded, and only the error component is evaluated at runtime.

### Multiple @unblaze blocks

You can use multiple `@unblaze` blocks in a single component:

```blade
@blaze(fold: true)

<div>
    <header>Static Header</header>

    @unblaze
        <div>Hello, {{ auth()->user()->name }}</div>
    @endunblaze

    <main>
        {{-- Lots of static content --}}
    </main>

    @unblaze
        <input type="hidden" value="{{ csrf_token() }}">
    @endunblaze

    <footer>Static Footer</footer>
</div>
```

Each `@unblaze` block creates an independent dynamic section, while everything else remains folded.

## Performance expectations

Blaze removes 94-97% of Blade's component rendering overhead. Here's what that means in practice:

**Most pages**: 10-50ms less time spent in component rendering
- Pages with dozens to hundreds of components
- Cumulative savings add up quickly
- More responsive page loads

**Heavy component pages**: 100-500ms+ savings
- Data tables with hundreds of rows
- Large dropdowns or select menus
- Dashboard grids with many cards
- Form pages with dozens of inputs
- Any page with heavy component usage

**Per-component savings**: 10-20 microseconds each
- May seem small, but compounds rapidly
- 100 components = 1-2ms saved
- 1,000 components = 10-20ms saved
- 10,000 components = 100-200ms saved

## Debugging

Blaze's function compilation (default `@blaze`) is designed to work transparently - it should work exactly like standard Blade components, just faster. If you encounter issues with the default behavior, it's likely a bug - please report it!

### Debugging folding

If you're using **folding** (`@blaze(fold: true)`) and encountering issues, here are common causes:

1. **Runtime-dependent code in template** - Using `@csrf`, `$errors`, `auth()`, etc.
   - **Solution:** Move dynamic code inside `@unblaze` blocks or pass data through props

2. **Dynamic props passed to folded component** - Component expects static props but receives `:prop="$var"`
   - **Solution:** Either pass static values, add prop to `safe` list, or use default `@blaze` instead

3. **Invalid prop definitions** - Syntax errors in `@props([...])`
   - **Solution:** Verify prop array syntax is valid PHP

4. **Missing `@aware` dependencies** - Child expects props not provided by parent
   - **Solution:** Ensure parent passes required props or child provides defaults

### Debug mode for folding

To get detailed error information when folding fails, enable debug mode:

```php
// In a service provider or debug environment...

app('blaze')->debug();
```

When debug mode is enabled:
- Blaze will throw exceptions instead of falling back gracefully to function compilation
- Shows exactly why a component can't be folded
- Helps identify invalid prop values, runtime dependencies, etc.
- **Note:** Only relevant for `@blaze(fold: true)` - function compilation rarely fails

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
