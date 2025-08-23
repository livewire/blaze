# 🔥 Blaze

Speed up your Laravel app by optimizing Blade component rendering performance.

> ⚠️ **Early stages** - This is an early-stage perimental package. APIs may change, and edge cases have yet to be worked out. Please test thoroughly and report any issues!

```
Rendering 25,000 simple button components:

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

To optimize a Blade component for performance, simply add the `@pure` directive at the top of your component file:

> **Using Flux?** All eligible Flux components are already marked with `@pure` - you don't need to do anything! Just install Blaze and enjoy the performance boost.

```blade
{{-- resources/views/components/button.blade.php --}}

@pure

@props(['variant' => 'primary'])

<button type="button" class="btn btn-{{ $variant }}">
    {{ $slot }}
</button>
```

When you use this component in your templates:

```blade
<x-button variant="secondary">
    Save
</x-button>
```

Blaze will automatically optimize it during compilation, pre-rendering the static parts while preserving dynamic content.

## Table of contents

- [When to use @pure](#when-to-use-pure)
- [Performance expectations](#performance-expectations)
- [Error detection](#error-detection)
- [Debugging](#debugging)
- [Performance benchmarks](#performance)
- [AI assistant integration](#ai-assistant-integration)

## When to use @pure

The `@pure` directive tells Blaze that a component has no runtime dependencies and can be safely optimized. Only add it to components that render the same way every time they're compiled.

### The @pure litmus test

Ask yourself these questions about your component:

1. **Does it work the same for all users?** (no auth checks, no user-specific content)
2. **Does it work the same on every request?** (no request data, no CSRF tokens)
3. **Does it work the same at any time?** (no timestamps, no "time ago" formatting)
4. **Does it only use the props you pass in?** (no session data, no database queries)
5. **Are all child components it renders also pure?** (no dynamic components hardcoded inside)

**If you answered YES to all questions → Add `@pure`**
**If you answered NO to any question → Don't add `@pure`**

### Quick mental model

Think of `@pure` components as **"design system" components** - they're the building blocks that:
- Look the same for everyone
- Only change based on props you explicitly pass
- Could be shown in a component library or Storybook without any application context

Examples: buttons, cards, badges, icons, layout grids, typography components

**Not** pure: anything that's "smart" or "connected" - forms (CSRF), navigation (active states), user avatars (auth), timestamps (time), paginated tables (request state).

**For developers familiar with functional programming**: Think of `@pure` components like pure functions - they always produce the same output for the same input, with no side effects or dependencies on external state.

### ✅ Safe for @pure

These components are good candidates for optimization:

```blade
{{-- Static UI components --}}

@pure

<div class="card p-4 rounded shadow">
    {{ $slot }}
</div>
```

```blade
{{-- Components that only depend on passed props --}}

@pure

@props(['size' => 'md', 'color' => 'blue'])

<button class="btn btn-{{ $size }} text-{{ $color }}">
    {{ $slot }}
</button>
```

### ❌ Never use @pure with

Avoid `@pure` for components that have runtime dependencies:

```blade
{{-- CSRF tokens change per request --}}

<form method="POST">
    @csrf <!-- ❌ Don't use @pure -->
    <button type="submit">Submit</button>
</form>
```

```blade
{{-- Authentication state changes at runtime --}}

@auth <!-- ❌ Don't use @pure -->
    <p>Welcome back!</p>
@endauth
```

```blade
{{-- Request data varies per request --}}

@props(['href'])

<a href="{{ $href }}" @class(['active' => request()->is($href)])> <!-- ❌ Don't use @pure -->
    {{ $slot }}
</a>
```

```blade
{{-- Error bags are request-specific --}}

@if($errors->has('email')) <!-- ❌ Don't use @pure -->
    <span class="error">{{ $errors->first('email') }}</span>
@endif
```

```blade
{{-- Session data changes at runtime --}}

<div>Welcome, {{ session('username') }}</div> <!-- ❌ Don't use @pure -->
```

```blade
{{-- Components using @aware --}}

@aware(['theme']) <!-- ❌ Don't use @pure -->

@props(['theme' => 'light'])

<div class="theme-{{ $theme }}">{{ $slot }}</div>
```

```blade
{{-- Pagination components --}}

@props(['paginator']) <!-- ❌ Don't use @pure -->

<div class="pagination">
    {{ $paginator->links() }}
</div>
```

```blade
{{-- Components containing non-pure children --}}

@pure <!-- ❌ WRONG: This table contains pagination which is dynamic -->

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

{{-- Components that CONTAIN other non-pure components --}}
@pure <!-- ❌ May break if child components are dynamic -->

<div class="wrapper">
    <x-user-greeting /> <!-- If this uses auth(), the parent can't be @pure -->
</div>
```

### Why isn't Blaze optimizing my component?

Even with `@pure`, Blaze only folds components when it can safely pre-render them at compile-time:

```blade
{{-- ✅ CAN be folded - static date value --}}
<x-date-formatter date="2024-01-15" />

{{-- ❌ CANNOT be folded - dynamic date variable --}}
<x-date-formatter :date="$user->created_at" />
```

**Why?** Blaze needs actual values at compile-time to pre-render. When you pass dynamic variables (like `$user->created_at`), Blaze doesn't know their values during compilation, so it skips folding and renders normally at runtime. This happens automatically - your component still works, it just won't be optimized.

**Note**: If your `@pure` component isn't being folded, check if you're passing dynamic variables to it. The component itself is fine - it's the dynamic data preventing optimization.

### 💡 Pro Tips

- **Start with simple components**: Begin with basic UI components like buttons, cards, and badges
- **Check your dependencies**: If your component uses any Laravel helpers or global variables, think twice
- **Test thoroughly**: After adding `@pure`, verify the component still works correctly across different requests
- **Blaze is forgiving**: If a component can't be optimized, Blaze will automatically fall back to normal rendering


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

### Error detection

When you add `@pure` to a component with runtime dependencies, Blaze will detect common unsafe patterns and show helpful error messages during compilation. This prevents broken components and guides you toward the correct implementation.

## Debugging

Blaze is designed to fail gracefully - when it encounters an error during component folding, it automatically falls back to normal Blade rendering. This ensures your application never breaks due to optimization attempts.

However, when debugging why a component isn't being optimized, you might want to see the actual error that's causing Blaze to skip folding:

```php
// In a service provider or debug environment
app('blaze')->debug();
```

When debug mode is enabled:
- Blaze will **not** fall back gracefully
- Instead, it will throw the actual exception that occurred during folding
- This helps you identify issues like:
  - Invalid prop types (e.g., passing a string to a date formatter expecting a Carbon instance)
  - Missing required props

## Performance

Blaze delivers significant performance improvements by eliminating the overhead of component rendering, prop parsing, and slot handling at runtime.

### Performance characteristics

- **Compilation overhead**: Minimal (~2-5ms per foldable component during first compile)
- **Memory usage**: Reduced at runtime (pre-rendered HTML uses less memory than component objects)
- **Cache efficiency**: Better template cache utilization due to fewer dynamic parts
- **Scaling**: Performance gains increase with component usage frequency

### When you'll see the biggest impact

- **Component-heavy applications** with lots of reusable UI elements
- **High-traffic sites** where every millisecond of render time matters
- **Dashboard/admin interfaces** with many repeated components
- **Design systems** with consistent, pure UI components

## AI assistant integration

This repository includes an [`AGENTS.md`](AGENTS.md) file specifically designed for AI assistants (like GitHub Copilot, Cursor, or Claude). If you're using an AI tool to help with your Laravel project:

1. **Point your AI assistant to the AGENTS.md file** when asking about Blaze optimization
2. **The file contains detailed guidance** for analyzing components and determining `@pure` eligibility
3. **Use it for automated analysis** - AI assistants can help audit your entire component library

Example prompts for AI assistants:
- "Using the AGENTS.md file, analyze my components and tell me which can use @pure"
- "Help me add @pure to all eligible components following the AGENTS.md guidelines"
- "Check if this component is safe for @pure based on AGENTS.md"

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
