# Blaze

Speed up your Laravel app by optimizing Blade component rendering performance.

```
Blade component overhead (25,000 renders):

Without Blaze  ████████████████████████████████████████  450ms
With Blaze     █                                          12ms
```

## Introduction

Blaze is a Laravel package that eliminates the overhead of Blade's component rendering pipeline. Standard Blade components go through class instantiation, attribute bag construction, view resolution, and multiple compilation passes on every render. Blaze compiles components into direct PHP function calls, removing this overhead entirely.

**Key benefits:**
- **94-97% less overhead** - eliminates Blade's component rendering pipeline
- **Drop-in replacement** - works with existing components
- **Compile-time optimization** - no runtime cost
- **Reliable** - no caching issues or stale data concerns

For users who want to squeeze out even more performance, Blaze offers **compile-time folding** - an advanced optimization that pre-renders components to static HTML, removing virtually 100% of the overhead.

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

### Optimization Strategies

**Available strategies:**

1. **Function compilation** (default) - The most reliable optimization
   - Compiles component into optimized PHP function
   - Works with any props (static or dynamic)
   - Removes 94-97% of Blade's component overhead
   - No caching concerns or stale data

2. **Compile-time folding** (`fold: true`) - The fastest but most restrictive
   - Pre-renders component at compile-time into static HTML
   - Requires understanding of what makes a component foldable
   - Removes virtually all overhead (~100%)
   - Automatically falls back to function compilation when needed

3. **Runtime memoization** (`memo: true`) - Caches rendered output
   - Caches output based on component name and props
   - Useful for self-closing components rendered multiple times with same props
   - Complements other strategies

### Which strategy should I use?

**For most components, use the default:**
```blade
@blaze
```

This removes 94-97% of Blade's component overhead with zero concerns about caching or stale data.

See the [Function compiler](#function-compiler) section for details.

**Use memoization for repeated self-closing components:**

Components like icons or avatars that appear multiple times on a page with the same props benefit from memoization:

```blade
@blaze(memo: true)
```

See the [Memoization](#memoization) section for details.

**Use folding for maximum performance:**

If you need every last bit of performance and are willing to understand the folding model, enable compile-time folding:

```blade
@blaze(fold: true)
```

See the [Compile-time folding](#compile-time-folding) section for details on what makes a component foldable.

## Table of contents

- [Function compiler](#function-compiler)
- [Configuration](#configuration)
- [Memoization](#memoization)
- [Compile-time folding](#compile-time-folding)
- [The @unblaze directive](#the-unblaze-directive)
- [Performance expectations](#performance-expectations)

## Function compiler

The function compiler transforms your Blade components into optimized PHP functions. This compiler has **full feature parity** with Laravel's Blade components while removing **94-97% of the rendering overhead**.

### How it works

When you add `@blaze` to a component, Blaze compiles it into a direct function call instead of going through Laravel's component resolution and rendering pipeline:

```blade
{{-- Standard Blade component --}}
<x-button variant="primary">Save</x-button>

{{-- Compiled by Blaze into --}}
<?php _c4f8e2a1b3d5f6e7($__blaze, ['variant' => 'primary'], ['default' => fn() => 'Save']); ?>
```

This eliminates the overhead of:
- Component class instantiation
- Attribute bag construction
- View factory resolution
- Multiple compilation passes

### Feature parity

The function compiler supports all standard Blade component features. However, some features are **disabled by default** to maximize performance. You can enable them on a per-folder basis if your components require them:

**Enabled by default:**
- `@props` with defaults and required props
- `@aware` for passing data down component trees
- Default and named slots (`$slot`, `<x-slot>`)
- `$attributes` bag with merge, class, style methods
- Kebab-case to camelCase prop conversion
- Dynamic attributes (`:href`, `:class`, etc.)
- Slot attributes
- Nested components

**Disabled by default (can be enabled):**
- View composers
- `View::share()` variables

See the [Configuration](#configuration) section to learn how to enable these features.

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

While Blaze supports all standard Blade component features, there are a few things that are not available:

**Not supported:**
- Dynamic component resolution (`<x-dynamic-component>`) - These fall back to standard Blade rendering
- Dynamic slot names (`<x-slot :name="$variable">`) - These fall back to standard Blade rendering
- Class-based components - Only anonymous components are supported
- `@aware` across Blade/Blaze boundaries - `@aware` only works when both parent and child use `@blaze`

## Configuration

By default, Blaze optimizes components with the `@blaze` directive and disables certain features for maximum performance. You can customize this behavior in your `AppServiceProvider`:

```php
use Illuminate\Support\Facades\Blade;

public function boot(): void
{
    // Enable Blaze for all components in a folder with custom options
    Blade::optimize()
        ->in(resource_path('views/components'))
        ->composer(true)    // Enable view composers
        ->share(true);      // Enable View::share() variables
}
```

### Available options

| Option | Default | Description |
|--------|---------|-------------|
| `composer` | `false` | Enable view composers for components in this folder |
| `share` | `false` | Enable `View::share()` variables (access via `$__env->shared('key')`) |

### Multiple folder configurations

You can configure different folders with different options:

```php
// Design system components - maximum performance
Blade::optimize()
    ->in(resource_path('views/components/ui'));

// Page components that need view composers
Blade::optimize()
    ->in(resource_path('views/components/pages'))
    ->composer(true);

// Legacy components that use shared variables
Blade::optimize()
    ->in(resource_path('views/components/legacy'))
    ->composer(true)
    ->share(true);
```

## Memoization

Runtime memoization caches the rendered output of self-closing components. When the same component is rendered multiple times with identical props, it only executes once.

```blade
@blaze(memo: true)

@props(['name', 'size' => 'md'])

<svg class="icon icon-{{ $size }}">
    {{-- SVG content for {{ $name }} --}}
</svg>
```

Memoization works even with dynamic props - the cache key is based on the actual prop values at runtime:

```blade
@foreach($items as $item)
    <x-icon :name="$item->icon" />  {{-- Each unique icon renders once --}}
@endforeach
```

Good candidates for memoization are components like icons or avatars - they typically have a small set of possible values (the same icons appear repeatedly) and are often rendered many times on a single page.

> **Note:** Memoization only works with self-closing components (no slots).

## Compile-time folding

Blaze supports **compile-time folding** with `@blaze(fold: true)` - an optimization that pre-renders components during compilation, removing virtually all component overhead.

### How folding works

When a component is folded, Blaze renders it at compile-time and embeds the resulting HTML directly into the compiled view. At runtime, there's zero component overhead - the HTML is already there, as if you'd written it by hand.

```blade
{{-- This component... --}}
<x-badge variant="success">Active</x-badge>

{{-- ...becomes this HTML at compile time --}}
<span class="badge badge-success">Active</span>
```

This is why folding removes virtually 100% of the overhead - the component no longer exists at runtime.

### A simple example

Here's a simple component that can be folded:

```blade
{{-- components/badge.blade.php --}}
@blaze(fold: true)

@props(['variant' => 'primary'])

<span class="badge badge-{{ $variant }}">{{ $slot }}</span>
```

When called with static values:

```blade
<x-badge variant="success">Active</x-badge>
```

Blaze pre-renders this at compile time into:

```html
<span class="badge badge-success">Active</span>
```

This is a simple example because it doesn't use any dynamic values - everything is known at compile time. But what happens when you pass dynamic values?

### Dynamic attributes and folding

When you pass a dynamic attribute to a foldable component, Blaze needs a way to handle it. Let's see how this works with a component that doesn't capture props:

```blade
{{-- components/box.blade.php --}}
@blaze(fold: true)

<div {{ $attributes }}>{{ $slot }}</div>
```

When you use this component with a dynamic attribute:

```blade
<x-box :class="$highlighted ? 'bg-yellow' : 'bg-white'">Content</x-box>
```

Blaze handles this through **placeholder replacement**:

1. Replace the dynamic value with a placeholder: `<x-box class="__PLACEHOLDER_1__">Content</x-box>`
2. Render the component: `<div class="__PLACEHOLDER_1__">Content</div>`
3. Replace the placeholder with the original PHP code: `<div class="<?php echo $highlighted ? 'bg-yellow' : 'bg-white'; ?>">Content</div>`

This works perfectly because the dynamic value just passes through `$attributes` unchanged.

### When dynamic props break folding

Now let's add `@props` and use the prop in logic:

```blade
{{-- components/status-badge.blade.php --}}
@blaze(fold: true)

@props(['status'])

@php
$color = match($status) {
    'active' => 'green',
    'pending' => 'yellow',
    'inactive' => 'gray',
    default => 'gray',
};
@endphp

<span class="badge bg-{{ $color }}">{{ $status }}</span>
```

If you call this with a dynamic prop:

```blade
<x-status-badge :status="$user->status" />
```

Blaze would try the placeholder approach:

1. Replace: `<x-status-badge status="__PLACEHOLDER_1__" />`
2. Render: The `match()` statement runs with `"__PLACEHOLDER_1__"` as the value
3. Result: `<span class="badge bg-gray">__PLACEHOLDER_1__</span>`

The `match()` hits the `default` case and bakes in `gray` forever! The dynamic status value gets replaced in the text content, but the color is permanently wrong.

**This is why Blaze aborts folding when a defined prop receives a dynamic value** - the prop might be used in logic that would produce incorrect results.

### The `safe` parameter

Sometimes you have a prop that's just passed through without any transformation:

```blade
{{-- components/heading.blade.php --}}
@blaze(fold: true)

@props(['title', 'level' => 2])

<h{{ $level }}>{{ $title }}</h{{ $level }}>
```

The `$title` prop is just echoed - no conditions, no transformations. It's safe to replace with a placeholder. You can tell Blaze this:

```blade
@blaze(fold: true, safe: ['title'])

@props(['title', 'level' => 2])

<h{{ $level }}>{{ $title }}</h{{ $level }}>
```

Now this works with dynamic titles:

```blade
<x-heading :title="$post->title" />  {{-- Folds successfully --}}
```

**The `safe: ['*']` wildcard:**

If all your props are just passed through (no conditions, no transformations), you can mark everything as safe:

```blade
@blaze(fold: true, safe: ['*'])
@props(['title', 'subtitle', 'author'])

<article>
    <h1>{{ $title }}</h1>
    <p class="subtitle">{{ $subtitle }}</p>
    <span class="author">{{ $author }}</span>
</article>
```

### The `unsafe` parameter

The `unsafe` parameter marks things that are normally safe as unsafe, forcing folding to abort if they contain dynamic values.

**Marking attributes as unsafe:**

Remember that attributes NOT captured as `@props` pass through safely by default. But if your component reads from `$attributes` to derive values, you need to mark attributes as unsafe:

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

Slots are NOT safe by default because their content could contain dynamic expressions. However, if your component inspects slot content programmatically, you may want to be explicit:

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

**The `unsafe: ['*']` wildcard:**

Forces folding to abort if ANY dynamic value is detected (props, attributes, or slots):

```blade
@blaze(fold: true, unsafe: ['*'])
{{-- Only folds if everything is completely static --}}
```

**Specifying slot names:**

You can mark specific named slots as unsafe:

```blade
@blaze(fold: true, unsafe: ['actions'])
```

### Global state and runtime dependencies

Beyond dynamic props, components can also fail to fold correctly if they depend on global state that changes between requests.

**Cannot fold components with:**

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

### The folding litmus test

Ask yourself these questions about your component:

1. **Does it work the same for all users?** (no auth checks, no user-specific content)
2. **Does it work the same on every request?** (no request data, no CSRF tokens)
3. **Does it work the same at any time?** (no timestamps, no "time ago" formatting)
4. **Does it only use the props you pass in?** (no session data, no database queries)
5. **Are all child components it renders also foldable?** (no non-foldable components inside)
6. **Are dynamic props either passed through unchanged OR marked as `safe`?**

**If you answered YES to all questions -> Can use `@blaze(fold: true)`**
**If you answered NO to any question -> Use default `@blaze` instead**

## The @unblaze directive

Sometimes you have a component that's *mostly* static, but contains a small dynamic section that would normally prevent it from being folded (like `$errors`, `request()`, or `session()`). The `@unblaze` directive lets you "punch a hole" in an otherwise foldable component, keeping the static parts pre-rendered while allowing specific sections to remain dynamic.

### The problem

Imagine a form input component that's perfect for folding - except it needs to show validation errors:

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

Without `@unblaze`, you have to choose: either skip folding entirely, or remove the error handling.

### The solution: @unblaze

The `@unblaze` directive creates a dynamic section within a folded component:

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

**What happens:**
- The `<div>`, `<label>`, and `<input>` are folded (pre-rendered at compile time)
- The error handling inside `@unblaze` remains dynamic (evaluated at runtime)
- You get the best of both worlds: folding optimization + dynamic functionality

### Alternative approach

Instead of using `@unblaze`, you can extract the dynamic part into a separate component:

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
@blaze

@props(['name'])

@error($name)
    <span>{{ $message }}</span>
@enderror
```

This approach is often simpler and more maintainable.

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

You can render other components inside `@unblaze` blocks:

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

> **TBD** - This section needs updated benchmarks.

Blaze removes 94-97% of Blade's component rendering overhead. The actual impact on your application depends on how many components you render per page.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
