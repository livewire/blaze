# 🔥 Blaze

Speed up your Laravel app by optimizing Blade component rendering performance.

> 🚀 **New blazingly fast function compiler!** Drop-in replacement for Blade components with full feature parity and 94-97% performance improvement. More reliable, no caching concerns, works everywhere.

```
Rendering 25,000 button components:

Without Blaze  ████████████████████████████████████████  450ms
With Blaze     █                                          12ms

                                                          97% faster
```

## Introduction

Blaze is a Laravel package that dramatically improves the rendering performance of your Blade components by compiling them into optimized PHP functions. Instead of going through Laravel's full component resolution and rendering pipeline on every request, Blaze components are compiled directly into fast, direct function calls.

**Key benefits:**
- **94-97% faster** than standard Blade components
- **Drop-in replacement** - works with existing components
- **No runtime overhead** - optimization happens at compile time
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
{{-- Function compilation (default) - 94-97% faster --}}
@blaze

{{-- Compile-time folding - 99.9% faster but only works with static props --}}
@blaze(fold: true)

{{-- Runtime memoization - caches component output --}}
@blaze(memo: true)
```

**Available strategies:**

1. **Function compilation** (default) - The most reliable optimization
   - Compiles component into optimized PHP function
   - Works with any props (static or dynamic)
   - 94-97% performance improvement
   - No caching concerns or stale data

2. **Compile-time folding** (`fold: true`) - The fastest but most restrictive
   - Pre-renders component at compile-time
   - Only works when all props are static values
   - 99.9% performance improvement
   - Automatically falls back to function compilation if props are dynamic

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

This gives you 94-97% improvement with zero concerns about caching or stale data.

**Use folding only if:**
- Component is always called with static props (rare)
- You need every last microsecond of performance
- You understand the caching implications

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
- [AI assistant integration](#ai-assistant-integration)

## New function compiler

Blaze includes a blazingly fast function compiler that transforms your Blade components into optimized PHP functions. This compiler has **full feature parity** with Laravel's Blade components while being **94-97% faster**.

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
- ✅ `@props` with defaults and required props
- ✅ `@aware` for passing data down component trees
- ✅ Default and named slots (`$slot`, `<x-slot>`)
- ✅ `$attributes` bag with merge, class, style methods
- ✅ Kebab-case to camelCase prop conversion
- ✅ Dynamic attributes (`:href`, `:class`, etc.)
- ✅ Slot attributes
- ✅ Nested components

### Benchmark results (25,000 components)

| Scenario | Standard Blade | Blaze | Improvement |
|----------|----------------|-------|-------------|
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

**Performance**: Consistent 94-97% improvement across all scenarios

**Simplicity**: Drop-in replacement - just add `@blaze` to your components

**No caching concerns**: No stale data, no cache invalidation complexity

**Full compatibility**: Supports every Blade component feature you're already using

### Limitations

While Blaze supports all standard Blade component features, there are a few advanced Laravel features that are not available:

**Not supported:**
- ❌ View composers - Components with view composers attached will need to pass data through props instead
- ❌ Custom `View::share()` variables - Shared variables are not automatically injected (use `$__env->shared('key')` to access them)
- ❌ Dynamic component resolution (`<x-dynamic-component>`) - These fall back to standard Blade rendering
- ❌ Dynamic slot names (`<x-slot :name="$variable">`) - These fall back to standard Blade rendering
- ❌ Class-based components - Only anonymous components are supported
- ❌ `@aware` across Blade/Blaze boundaries - @aware only works when both parent and child use `@blaze`

## When to use @blaze

**Simple answer: Use `@blaze` on any component!**

With the function compiler, `@blaze` can be safely added to virtually any Blade component. It's a drop-in replacement that works with:

✅ Static components (buttons, cards, badges)  
✅ Dynamic components (user profiles, data tables)  
✅ Components with `@props`  
✅ Components with `$attributes`  
✅ Components with slots  
✅ Components with `@aware`  
✅ Nested components  
✅ Components that accept dynamic data through props

> **Note:** Looking for information about folding restrictions (auth checks, CSRF tokens, etc.)? See the [Advanced: Compile-time folding](#advanced-compile-time-folding) section.

### Function compilation vs Folding

Blaze offers two optimization strategies with different capabilities:

| Feature | Function Compilation (`@blaze`) | Folding (`@blaze(fold: true)`) |
|---------|--------------------------------|-------------------------------|
| **Performance** | 94-97% faster | 99.9% faster |
| **Dynamic props** (`:prop="$var"`) | ✅ Fully supported | ⚠️ Works unless transformed* |
| **Static props** (`prop="value"`) | ✅ Fully supported | ✅ Pre-rendered |
| **`@props` with defaults** | ✅ Fully supported | ✅ Supported |
| **`@aware` directive** | ✅ Always works | ⚠️ Only with `aware: true` param |
| **`$errors` variable** | ✅ Works anywhere | ⚠️ Only inside `@unblaze` blocks |
| **`@csrf` / `@method`** | ✅ Works anywhere | ⚠️ Only inside `@unblaze` blocks |
| **`auth()` / `session()`** | ✅ Works anywhere | ⚠️ Only inside `@unblaze` blocks |
| **`request()` / `old()`** | ✅ Works anywhere | ⚠️ Only inside `@unblaze` blocks |
| **`now()` / timestamps** | ✅ Works anywhere | ⚠️ Only inside `@unblaze` blocks |
| **Nested components** | ✅ Any component | ⚠️ Only foldable children |
| **Cache invalidation** | ✅ No caching | ⚠️ Requires cache management |
| **Reliability** | ✅ 100% predictable | ⚠️ Depends on usage |

**Understanding "Works unless transformed":**

When a component marked with `@blaze(fold: true)` receives dynamic props (`:title="$user->name"`), Blaze is smart enough to handle them - it preserves the dynamic values and passes them through during folding. The component can still be pre-rendered at compile time with 99.9% optimization.

**However**, this only works if props **flow through unchanged**. If your component **transforms props** inside the template before using them, folding breaks:

```blade
{{-- ❌ This won't fold correctly - prop transformation inside component --}}
@blaze(fold: true)

@props(['title'])

@php
    $title = Str::title($title); // Transforms the prop
@endphp

<h1>{{ $title }}</h1>

{{-- ✅ This works fine - no prop transformation --}}
@blaze(fold: true)

@props(['title'])

<h1>{{ Str::title($title) }}</h1> <!-- Transformation in output only -->
```

When props are transformed inside the component (using `@php` blocks or similar), Blaze can't fold properly because the transformation happens before folding. Use function compilation (`@blaze`) for these components instead.

**Recommendation**: Use the default `@blaze` (function compilation) for 99% of components. Only use `@blaze(fold: true)` if you need the absolute maximum performance and understand the restrictions.

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

Blaze also supports **compile-time folding** with `@blaze(fold: true)` - an extreme optimization that pre-renders components during compilation for 99.9% performance improvement. However, folding only works under specific conditions.

### When to use folding

Use `@blaze(fold: true)` only if:
1. Component is **always** called with static prop values (no `:prop="$variable"`)
2. Component does **not transform props** inside the template (no `$title = Str::title($title)`)
3. Component has no runtime dependencies (no `@csrf`, `$errors`, `auth()`, etc.)
4. You need the absolute maximum performance (extra 3-5% over function compilation)

```blade
{{-- Example: This can be folded --}}
@blaze(fold: true)

@props(['variant' => 'primary'])

<button class="btn-{{ $variant }}">{{ $slot }}</button>

{{-- Called with static prop --}}
<x-button variant="secondary">Save</x-button>
```

If props are dynamic, folding automatically falls back to function compilation:

```blade
{{-- This will use function compilation (not folded) --}}
<x-button :variant="$userRole">Save</x-button>
```

### The folding litmus test

Ask yourself these questions about your component:

1. **Does it work the same for all users?** (no auth checks, no user-specific content)
2. **Does it work the same on every request?** (no request data, no CSRF tokens)
3. **Does it work the same at any time?** (no timestamps, no "time ago" formatting)
4. **Does it only use the props you pass in?** (no session data, no database queries)
5. **Are all child components it renders also foldable?** (no dynamic components hardcoded inside)
6. **Is it always called with static props?** (no `:prop="$variable"`)

**If you answered YES to all questions → Can use `@blaze(fold: true)`**  
**If you answered NO to any question → Use default `@blaze` instead**

### ❌ Cannot fold components with

Avoid `@blaze(fold: true)` for components that have runtime dependencies:

```blade
{{-- CSRF tokens change per request --}}
<form method="POST">
    @csrf <!-- ❌ Can't fold -->
    <button type="submit">Submit</button>
</form>
```

```blade
{{-- Authentication state changes at runtime --}}
@auth <!-- ❌ Can't fold -->
    <p>Welcome back!</p>
@endauth
```

```blade
{{-- Error bags are request-specific --}}
@if($errors->has('email')) <!-- ❌ Can't fold -->
    <span class="error">{{ $errors->first('email') }}</span>
@endif
```

```blade
{{-- Session data changes at runtime --}}
<div>Welcome, {{ session('username') }}</div> <!-- ❌ Can't fold -->
```

```blade
{{-- Request data varies per request --}}
<div @class(['active' => request()->is('/')])>Home</div> <!-- ❌ Can't fold -->
```

```blade
{{-- Time-dependent content --}}
<p>Generated on {{ now() }}</p> <!-- ❌ Can't fold -->
```

```blade
{{-- Components with dynamic props --}}
@blaze(fold: true)
<button>Save</button>

{{-- ❌ Won't fold - dynamic prop --}}
<x-button :variant="$user->role">Save</x-button>
```

### When folding makes sense

**Good use case** - Static UI components in a design system that are always called with literals:

```blade
@blaze(fold: true)

@props(['icon', 'label'])

<button class="btn">
    <x-icon :name="$icon" />
    {{ $label }}
</button>

{{-- Always called like this (static props) --}}
<x-button icon="save" label="Save" />
<x-button icon="delete" label="Delete" />
```

**Bad use cases:**

```blade
{{-- ❌ Receives dynamic props --}}
@blaze(fold: true)

@props(['user'])

<div>{{ $user->name }}</div>

{{-- Called like this (dynamic prop prevents folding) --}}
<x-user-card :user="$currentUser" />
```

```blade
{{-- ❌ Transforms props inside component --}}
@blaze(fold: true)

@props(['title'])

@php
    $title = Str::title($title); // Prop transformation prevents proper folding
@endphp

<h1>{{ $title }}</h1>
```

For these cases, use the default `@blaze` (function compilation) instead - you still get 94-97% improvement without the folding restrictions.

## Making impure components foldable with @unblaze

> **Note:** The `@unblaze` directive is primarily useful with **folding** (`@blaze(fold: true)`). With the default function compilation strategy, you typically don't need `@unblaze` - just pass dynamic data through props instead.

Sometimes you have a component that's *mostly* static, but contains a small dynamic section that would normally prevent it from being folded (like `$errors`, `request()`, or `session()`). The `@unblaze` directive lets you "punch a hole" in an otherwise foldable component, keeping the static parts pre-rendered while allowing specific sections to remain dynamic.

### The problem

Imagine a form input component that's perfect for `@blaze` - except it needs to show validation errors:

```blade
{{-- ❌ Can't use @blaze(fold: true) - $errors prevents folding --}}

<div>
    <label>{{ $label }}</label>
    <input type="text" name="{{ $name }}">

    @if($errors->has($name))
        <span>{{ $errors->first($name) }}</span>
    @endif
</div>
```

Without `@unblaze`, you have to choose: either skip folding entirely (losing the 99.9% speed boost), or remove the error handling (losing functionality).

### The solution: @unblaze

The `@unblaze` directive creates a dynamic section within a folded component:

```blade
{{-- ✅ Now we can use folding! --}}

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

Blaze provides consistent 94-97% improvement in component rendering time. Here's what that means in practice:

**Most pages**: 10-50ms faster total render time
- Pages with dozens to hundreds of components
- Cumulative savings add up quickly
- More responsive page loads

**Heavy component pages**: 100-500ms+ faster
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
   - **Solution:** Either pass static values or use default `@blaze` instead

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

## AI assistant integration

This repository includes an [`AGENTS.md`](AGENTS.md) file specifically designed for AI assistants (like GitHub Copilot, Cursor, or Claude). The file contains detailed guidance for analyzing components and determining **folding eligibility** (`@blaze(fold: true)`).

If you're using an AI tool to help audit which components can be folded:

1. **Point your AI assistant to the AGENTS.md file** when asking about folding optimization
2. **The file contains the folding litmus test** and detailed rules for what can/cannot be folded
3. **Use it for automated analysis** - AI assistants can help audit your component library for folding candidates

Example prompts for AI assistants:
- "Using the AGENTS.md file, analyze my components and tell me which can use @blaze(fold: true)"
- "Help me identify foldable components following the AGENTS.md guidelines"
- "Check if this component is safe for folding based on AGENTS.md"

**Note:** For general `@blaze` usage (function compilation), you don't need special analysis - it works on virtually any component!

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
