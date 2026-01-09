# 🔥 Blaze

Speed up your Laravel app by optimizing Blade component rendering performance.

> ⚠️ **Early stages** - This is an early-stage experimental package. APIs may change, and edge cases have yet to be worked out. Please test thoroughly and report any issues!

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
composer require livewire/blaze:^1.0@beta
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

- [Migration guide (v1.x → v2.0)](#migration-guide-v1x--v20)
- [When to use @blaze](#when-to-use-blaze)
- [Making impure components Blaze-eligible with @unblaze](#making-impure-components-blaze-eligible-with-unblaze)
- [Performance expectations](#performance-expectations)
- [Debugging](#debugging)
- [AI assistant integration](#ai-assistant-integration)

## Migration guide (v1.x → v2.0)

### Breaking change: New default behavior

In v2.0, the default behavior of `@blaze` has changed:

**v1.x (old):**
```blade
@blaze  {{-- Defaulted to folding (compile-time pre-rendering) --}}
```

**v2.0 (new):**
```blade
@blaze  {{-- Defaults to function compilation (94-97% faster, works with all props) --}}
```

### Why the change?

**Folding** (the old default) is extremely fast (99.9% improvement) but has limitations:
- Only works when all props are static literals
- Falls back silently when props are dynamic
- Can cause confusion about when optimization applies
- Requires cache invalidation management

**Function compilation** (the new default) is more reliable:
- Works with both static and dynamic props
- No caching concerns or stale data issues
- Predictable 94-97% performance improvement
- Drop-in replacement for standard components

### How to migrate

**Most users: No changes needed**

If your components work correctly now, they'll continue working with v2.0. The new default is more reliable and still provides 94-97% improvement.

**If you need the old folding behavior:**

Add `fold: true` explicitly to components where you know props are always static:

```blade
{{-- v1.x --}}
@blaze

{{-- v2.0 equivalent --}}
@blaze(fold: true)
```

**Recommended approach:**

Start with the new default and only add `fold: true` if you:
1. Know the component is always called with static props
2. Need the extra 3-5% performance gain
3. Understand the caching implications

### Performance comparison

| Version | Default strategy | Static props | Dynamic props |
|---------|-----------------|--------------|---------------|
| v1.x | Folding | 99.9% faster | Falls back (no optimization) |
| v2.0 | Function compilation | 94-97% faster | 94-97% faster |

The new default is slightly slower with static props but **dramatically faster** with dynamic props (the common case).

## When to use @blaze

With the default function compilation strategy, `@blaze` can be safely added to **most components** in your application. The main requirement is that the component doesn't access runtime-specific data at the **template level** (auth state, request data, CSRF tokens, etc.).

### The @blaze rule

**You can use `@blaze` if your component:**
- Renders based only on props passed to it
- Doesn't directly access auth, session, request, or other runtime globals in the template
- Doesn't use `@csrf`, `$errors`, `old()`, or similar runtime helpers

**You cannot use `@blaze` if your component:**
- Uses `@auth`, `@guest`, `auth()->user()` directly in the template
- Uses `@csrf`, `@method`, or form helpers
- Accesses `$errors` or validation state
- Uses `request()`, `session()`, or `old()` helpers
- Uses `@error`, `@once`, or other runtime-dependent directives

### Quick mental model

Think of `@blaze` components as **prop-driven components** - they:
- Accept data through props
- Render based only on those props
- Don't reach out to global state

**Examples:** buttons, cards, badges, icons, layout components, typography, forms inputs (without validation display), navigation items (active state passed as prop)

**Not eligible:** auth-dependent navigation, forms with CSRF tokens, validation error displays, components with `@once` blocks

**For developers familiar with React**: Think of `@blaze` components like React function components - they receive props and return markup, without accessing global context or runtime state directly.

### ✅ Safe for @blaze

These components are good candidates for optimization:

```blade
{{-- Simple UI components --}}

@blaze

<div class="card p-4 rounded shadow">
    {{ $slot }}
</div>
```

```blade
{{-- Components with props --}}

@blaze

@props(['size' => 'md', 'color' => 'blue'])

<button class="btn btn-{{ $size }} text-{{ $color }}">
    {{ $slot }}
</button>
```

```blade
{{-- Components that accept dynamic data through props --}}

@blaze

@props(['user'])

<div class="user-card">
    <h3>{{ $user->name }}</h3>
    <p>Joined {{ $user->created_at->format('M Y') }}</p>
</div>
```

```blade
{{-- Navigation items with active state passed as prop --}}

@blaze

@props(['href', 'active' => false])

<a href="{{ $href }}" @class(['active' => $active])>
    {{ $slot }}
</a>
```

### ❌ Never use @blaze with

Avoid `@blaze` for components that have runtime dependencies:

```blade
{{-- CSRF tokens change per request --}}

<form method="POST">
    @csrf <!-- ❌ Don't use @blaze -->
    <button type="submit">Submit</button>
</form>
```

```blade
{{-- Authentication state changes at runtime --}}

@auth <!-- ❌ Don't use @blaze -->
    <p>Welcome back!</p>
@endauth
```

```blade
{{-- Request data varies per request --}}

@props(['href'])

<!-- ❌ Don't use @blaze - request() is runtime-dependent -->
<a href="{{ $href }}" @class(['active' => request()->is($href)])>
    {{ $slot }}
</a>

<!-- ✅ Instead, pass active state as a prop -->
@blaze

@props(['href', 'active' => false])

<a href="{{ $href }}" @class(['active' => $active])>
    {{ $slot }}
</a>
```

```blade
{{-- Error bags are request-specific --}}

@if($errors->has('email')) <!-- ❌ Don't use @blaze -->
    <span class="error">{{ $errors->first('email') }}</span>
@endif
```

```blade
{{-- Session data changes at runtime --}}

<div>Welcome, {{ session('username') }}</div> <!-- ❌ Don't use @blaze -->
```

```blade
{{-- Pagination components --}}

@props(['paginator']) <!-- ❌ Don't use @blaze -->

<div class="pagination">
    {{ $paginator->links() }}
</div>
```

```blade
{{-- Components containing non-foldable children --}}

@blaze <!-- ❌ WRONG: This table contains pagination which is dynamic -->

@props(['items'])

<table class="table">
    @foreach($items as $item)
        <tr><td>{{ $item->name }}</td></tr>
    @endforeach

    <x-table-pagination :paginator="$items" />
</table>
```

### 🔍 Watch out for

Be careful with these patterns that might seem safe but can cause issues:

```blade
{{-- Time-dependent content --}}
<p>Generated on {{ now() }}</p> <!-- Changes every request -->

{{-- User-specific content --}}
<p>Hello {{ auth()->user()->name }}</p> <!-- Different per user -->

{{-- Environment-dependent values --}}
<script src="{{ config('app.cdn_url') }}/app.js"></script> <!-- Might change -->

{{-- Components that CONTAIN other non-foldable components --}}
@blaze <!-- ❌ May break if child components are dynamic -->

<div class="wrapper">
    <x-user-greeting /> <!-- If this uses auth(), the parent can't be @blaze -->
</div>
```

### Understanding the optimization

With default function compilation, **all `@blaze` components are optimized** - whether you pass static or dynamic props:

```blade
{{-- ✅ Both are optimized with function compilation --}}
<x-button variant="primary">Save</x-button>
<x-button :variant="$user->role">Save</x-button>
```

The difference between **function compilation** (default) and **folding** (`fold: true`):

| Strategy | Static props | Dynamic props | Speed | Reliability |
|----------|--------------|---------------|-------|-------------|
| Function compilation (default) | ✅ Optimized | ✅ Optimized | 94-97% faster | 100% |
| Folding (`fold: true`) | ✅ Pre-rendered | ⚠️ Falls back to functions | 99.9% faster | 95% |

**Function compilation** works with any props, **folding** only works when all props are static literals.

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

### 💡 Pro Tips

- **Start with simple components**: Begin with basic UI components like buttons, cards, and badges
- **Check your dependencies**: If your component uses any Laravel helpers or global variables, think twice
- **Test thoroughly**: After adding `@blaze`, verify the component still works correctly across different requests
- **Blaze is forgiving**: If a component can't be optimized, Blaze will automatically fall back to normal rendering

## Making impure components Blaze-eligible with @unblaze

> **Note:** The `@unblaze` directive is primarily useful with **folding** (`@blaze(fold: true)`). With the default function compilation strategy, you typically don't need `@unblaze` - just pass dynamic data through props instead.

Sometimes you have a component that's *mostly* static, but contains a small dynamic section that would normally prevent it from being folded (like `$errors`, `request()`, or `session()`). The `@unblaze` directive lets you "punch a hole" in an otherwise foldable component, keeping the static parts pre-rendered while allowing specific sections to remain dynamic.

### The problem

Imagine a form input component that's perfect for `@blaze` - except it needs to show validation errors:

```blade
{{-- ❌ Can't use @blaze - $errors prevents optimization --}}

<div>
    <label>{{ $label }}</label>
    <input type="text" name="{{ $name }}">

    @if($errors->has($name))
        <span>{{ $errors->first($name) }}</span>
    @endif
</div>
```

Without `@unblaze`, you have to choose: either skip `@blaze` entirely (losing all optimization), or remove the error handling (losing functionality).

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
@blaze

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
@blaze

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
@blaze

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

Blaze provides consistent 94-97% improvement in component rendering time across all scenarios. Here's what that means in practice:

### Benchmark Results (25,000 components)

| Scenario | Standard Blade | Blaze | Improvement |
|----------|----------------|-------|-------------|
| Simple components | 451ms | 12ms | 97.3% |
| With attributes | 462ms | 16ms | 96.5% |
| With merge() | 635ms | 16ms | 97.5% |
| With class() | 546ms | 18ms | 96.8% |
| Props + attributes | 758ms | 25ms | 96.7% |
| Default slots | 430ms | 20ms | 95.3% |
| Named slots | 614ms | 35ms | 94.3% |
| @aware (nested) | 1,835ms | 61ms | 96.7% |

### Real-world impact

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

Blaze is designed to work transparently - components with `@blaze` should work exactly like standard Blade components, just faster.

### Compilation issues

If you encounter errors after adding `@blaze`, the most common causes are:

1. **Runtime-dependent code in template** - Using `@csrf`, `$errors`, `auth()`, etc.
   - **Solution:** Remove `@blaze` or move dynamic code to parent component

2. **Invalid prop definitions** - Syntax errors in `@props([...])`
   - **Solution:** Verify prop array syntax is valid PHP

3. **Missing `@aware` dependencies** - Child expects props not provided by parent
   - **Solution:** Ensure parent passes required props or child provides defaults

### Debug mode

For detailed error information when using folding (`fold: true`), enable debug mode:

```php
// In a service provider or debug environment...

app('blaze')->debug();
```

When debug mode is enabled:
- Blaze will throw exceptions instead of falling back gracefully
- Helps identify issues with folding (invalid props, missing dependencies, etc.)
- Only relevant when using `fold: true` - function compilation rarely fails

## AI assistant integration

This repository includes an [`AGENTS.md`](AGENTS.md) file specifically designed for AI assistants (like GitHub Copilot, Cursor, or Claude). If you're using an AI tool to help with your Laravel project:

1. **Point your AI assistant to the AGENTS.md file** when asking about Blaze optimization
2. **The file contains detailed guidance** for analyzing components and determining `@blaze` eligibility
3. **Use it for automated analysis** - AI assistants can help audit your entire component library

Example prompts for AI assistants:
- "Using the AGENTS.md file, analyze my components and tell me which can use @blaze"
- "Help me add @blaze to all eligible components following the AGENTS.md guidelines"
- "Check if this component is safe for @blaze based on AGENTS.md"

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
