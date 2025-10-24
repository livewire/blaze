# 🔥 Blaze

Speed up your Laravel app by optimizing Blade component rendering performance.

> ⚠️ **Early stages** - This is an early-stage experimental package. APIs may change, and edge cases have yet to be worked out. Please test thoroughly and report any issues!

```
Rendering 25,000 foldable button components:

Without Blaze  ████████████████████████████████████████  750ms
With Blaze     ██                                         45ms

                                                          ~17x faster
```

## Introduction

Blaze is a Laravel package that dramatically improves the rendering performance of your Blade components through compile-time optimization. It identifies static portions of your templates and pre-renders them, removing much of Blade's runtime overhead.

## Installation

You can install the package via composer:

```bash
composer require livewire/blaze
```

## Usage

To optimize a Blade component for performance, simply add the `@blaze` directive at the top of your component file.

The `@blaze` directive signals that your component is "foldable" - meaning it has no side effects and always renders the same output for the same input (no auth checks, no database queries, no time-dependent content). Think of these as your basic UI building blocks like buttons, cards, and badges.

> **Using Flux?** All eligible Flux components are already marked with `@blaze` - you don't need to do anything! Just install Blaze and enjoy the performance boost.

```blade
{{-- resources/views/components/button.blade.php --}}

@blaze

@props(['variant' => 'primary'])

<button type="button" class="btn btn-{{ $variant }}">
    {{ $slot }}
</button>
```

The `@blaze` directive supports optional parameters to control different optimization strategies:

```blade
{{-- All optimizations enabled (default) --}}
@blaze

{{-- Explicitly enable all optimizations --}}
@blaze(fold: true, memo: true, aware: true)

{{-- Disable specific optimizations --}}
@blaze(fold: false, memo: true, aware: false)
```

**Parameters:**
- `fold: true/false` - Enable compile-time code folding (default: true)
- `memo: true/false` - Enable runtime memoization (default: true)
- `aware: true/false` - Enable `@aware` directive support (default: true)

### How Optimization Works

Blaze uses a two-tier optimization approach:

1. **Compile-time folding** - Pre-renders static components during Blade compilation
2. **Runtime memoization** - Caches component output when folding isn't possible

If a component can't be folded (due to dynamic content), Blaze automatically falls back to memoization, caching the rendered output based on the component name and props to avoid re-rendering identical components.

When you use this component in your templates:

```blade
<x-button variant="secondary">
    Save
</x-button>
```

Blaze will automatically optimize it during compilation, pre-rendering the static parts while preserving dynamic content.

## Table of contents

- [When to use @blaze](#when-to-use-blaze)
- [Making impure components Blaze-eligible with @unblaze](#making-impure-components-blaze-eligible-with-unblaze)
- [Performance expectations](#performance-expectations)
- [Debugging](#debugging)
- [AI assistant integration](#ai-assistant-integration)

## When to use @blaze

The `@blaze` directive tells Blaze that a component has no runtime dependencies and can be safely optimized. Only add it to components that render the same way every time they're compiled.

### The @blaze litmus test

Ask yourself these questions about your component:

1. **Does it work the same for all users?** (no auth checks, no user-specific content)
2. **Does it work the same on every request?** (no request data, no CSRF tokens)
3. **Does it work the same at any time?** (no timestamps, no "time ago" formatting)
4. **Does it only use the props you pass in?** (no session data, no database queries)
5. **Are all child components it renders also foldable?** (no dynamic components hardcoded inside)

**If you answered YES to all questions → Add `@blaze`**
**If you answered NO to any question → Don't add `@blaze`**

### Quick mental model

Think of `@blaze` components as **"design system" components** - they're the building blocks that:
- Look the same for everyone
- Only change based on props you explicitly pass
- Could be shown in a component library without any application context

Examples: buttons, cards, badges, icons, layout grids, typography components

**Not foldable** : anything that's "smart" or "connected" - forms (CSRF), navigation (active states), user avatars (auth), timestamps (time), paginated tables (request state).

**For developers familiar with functional programming**: Think of `@blaze` components like pure functions - they always produce the same output for the same input, with no side effects or dependencies on external state.

### ✅ Safe for @blaze

These components are good candidates for optimization:

```blade
{{-- Static UI components --}}

@blaze

<div class="card p-4 rounded shadow">
    {{ $slot }}
</div>
```

```blade
{{-- Components that only depend on passed props --}}

@blaze

@props(['size' => 'md', 'color' => 'blue'])

<button class="btn btn-{{ $size }} text-{{ $color }}">
    {{ $slot }}
</button>
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

<a href="{{ $href }}" @class(['active' => request()->is($href)])> <!-- ❌ Don't use @blaze -->
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

### Why isn't Blaze optimizing my component?

Even with `@blaze`, Blaze only folds components when it can safely pre-render them at compile-time:

```blade
{{-- ✅ CAN be folded - static date value --}}
<x-date-formatter date="2024-01-15" />

{{-- ❌ CANNOT be folded - dynamic date variable --}}
<x-date-formatter :date="$user->created_at" />
```

**Why?** Blaze needs actual values at compile-time to pre-render. When you pass dynamic variables (like `$user->created_at`), Blaze doesn't know their values during compilation, so it skips folding and renders normally at runtime. This happens automatically - your component still works, it just won't be optimized.

**Note**: If your `@blaze` component isn't being folded, check if you're passing dynamic variables to it. The component itself is fine - it's the dynamic data preventing optimization.

### Runtime Memoization

When a component can't be folded due to dynamic content, Blaze automatically falls back to **runtime memoization**. This caches the rendered output based on the component name and props, so identical components don't need to be re-rendered.

```blade
{{-- This component can't be folded but will be memoized --}}

@blaze

@props(['user'])

<div class="user-card">
    <h3>{{ $user->name }}</h3>
    <p>Joined {{ $user->created_at->format('M Y') }}</p>
</div>
```

**Benefits of memoization:**
- Caches identical component renders
- Reduces CPU usage for repeated components
- Works with any `@blaze` component, even with dynamic props
- Automatic fallback when folding isn't possible

### 💡 Pro Tips

- **Start with simple components**: Begin with basic UI components like buttons, cards, and badges
- **Check your dependencies**: If your component uses any Laravel helpers or global variables, think twice
- **Test thoroughly**: After adding `@blaze`, verify the component still works correctly across different requests
- **Blaze is forgiving**: If a component can't be optimized, Blaze will automatically fall back to normal rendering

## Making impure components Blaze-eligible with @unblaze

Sometimes you have a component that's *mostly* static, but contains a small dynamic section that would normally prevent it from being folded (like `$errors`, `request()`, or `session()`). The `@unblaze` directive lets you "punch a hole" in an otherwise static component, keeping the static parts optimized while allowing specific sections to remain dynamic.

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
{{-- ✅ Now we can use @blaze! --}}

@blaze

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
- You get the best of both worlds: optimization + dynamic functionality

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

While our benchmark shows up to 17x improvement for rendering thousands of components, real-world gains are more nuanced:

### Typical improvements

**Most pages**: 10-30ms faster rendering
- Reasonably sized pages with a few hundred components will see modest but meaningful improvements

**Heavy component pages**: 50-100ms+ faster
- Data tables with dozens/hundreds of rows
- Select dropdowns with many options
- Dashboard grids with repeated cards
- Any page with significant component repetition

## Debugging

Blaze is designed to fail gracefully - when it encounters an error during component folding, it automatically falls back to normal Blade rendering. This ensures your application never breaks due to optimization attempts.

However, when debugging why a component isn't being optimized, you might want to see the actual error that's causing Blaze to skip folding:

```php
// In a service provider or debug environment...

app('blaze')->debug();
```

When debug mode is enabled:
- Blaze will **not** fall back gracefully
- Instead, it will throw the actual exception that occurred during folding
- This helps you identify issues like:
  - Invalid prop types (e.g., passing a string to a date formatter expecting a Carbon instance)
  - Missing required props

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
