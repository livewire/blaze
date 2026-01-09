# Laravel Blaze - Agent Guidance for Folding

> **Important:** This document is specifically about **compile-time folding** (`@blaze(fold: true)`), an advanced optimization strategy in Laravel Blaze. For general Blaze usage, the default function compilation (`@blaze`) works on virtually any component and doesn't need this analysis.

This document provides guidance for AI assistants helping users identify which components can use **compile-time folding** - an extreme optimization that pre-renders components at compile-time for 99.9% performance improvement.

## Overview

Laravel Blaze offers two optimization strategies:

1. **Function compilation** (`@blaze`) - 94-97% faster, works on any component (recommended default)
2. **Compile-time folding** (`@blaze(fold: true)`) - 99.9% faster, but only works under specific conditions

This document focuses on **folding analysis** - helping identify components that meet the strict requirements for `@blaze(fold: true)`.

### How Folding Works

When a component uses `@blaze(fold: true)`, Blaze:

1. Identifies components marked with `@blaze(fold: true)` in their source
2. Analyzes component source for runtime dependencies
3. Pre-renders eligible components during Blade compilation with static props
4. Falls back to function compilation for components with dynamic props or runtime dependencies

## Core Concepts

### The @blaze(fold: true) Directive

The `@blaze(fold: true)` directive tells Blaze that a component can be pre-rendered at compile-time. It must be placed at the top of a component file:

```blade
@blaze(fold: true)

@props(['title'])

<h1 class="text-2xl font-bold">{{ $title }}</h1>
```

**Requirements for folding:**
- Component must have no runtime dependencies (no `@csrf`, `$errors`, `auth()`, etc.)
- Component must be called with static prop values (no `:prop="$variable"`)
- All nested child components must also be foldable

**Optional parameters:**
```blade
{{-- Enable folding with @aware support --}}
@blaze(fold: true, aware: true)

{{-- Enable folding with memoization fallback --}}
@blaze(fold: true, memo: true)
```

### Folding Process

When a component uses `@blaze(fold: true)`, Blaze:
1. Checks if all props are static values (not dynamic `:prop="$var"`)
2. Replaces dynamic content being passed in via attributes or slots with placeholders
3. Renders the component with placeholders at compile-time
3. Validates that placeholders are preserved
4. Replaces placeholders with original dynamic content
5. Outputs the optimized HTML directly into the parent template

### Fallback Behavior

When a component can't be folded (due to dynamic props or runtime dependencies), Blaze automatically falls back to **function compilation**, which still provides 94-97% performance improvement.

## Helping Users Analyze Components for Folding

When a user asks about adding `@blaze(fold: true)` to a component or wants you to analyze which components can be folded, follow this process:

### 1. Read and Analyze the Component

First, examine the component source code for:
- Runtime dependencies that prevent folding (see unsafe patterns below)
- Whether component is always called with static prop values
- Whether nested child components are also foldable

### 2. Foldable Component Patterns

Components can use `@blaze(fold: true)` when they:
- Accept props and render them consistently
- Have no runtime dependencies (`@csrf`, `$errors`, `auth()`, etc.)
- Are always called with static props (not `:prop="$variable"`)
- Only contain other foldable child components

Examples:
```blade
{{-- UI components with no props --}}
@blaze(fold: true)

<div class="card p-4 bg-white rounded shadow">{{ $slot }}</div>

{{-- Static prop-based styling --}}
@blaze(fold: true)

@props(['variant' => 'primary'])

<button class="btn btn-{{ $variant }}">{{ $slot }}</button>

{{-- Called like: <x-button variant="secondary">Save</x-button> --}}

{{-- Simple formatting (if always called with static props) --}}
@blaze(fold: true)

@props(['price'])

<span class="font-mono">${{ number_format($price, 2) }}</span>

{{-- Called like: <x-price price="19.99" /> --}}

{{-- Components using @aware (requires aware: true param) --}}
@blaze(fold: true, aware: true)

@aware(['theme'])
@props(['theme' => 'light'])

<div class="theme-{{ $theme }}">{{ $slot }}</div>
```

### 3. Patterns That Prevent Folding

These patterns mean the component **cannot use `@blaze(fold: true)`** (but can use default `@blaze` for function compilation):

**Authentication & Authorization:**
- `@auth`, `@guest`, `@can`, `@cannot`
- `auth()`, `user()`
- Any user-specific content

**Request Data:**
- `request()` or `Request::` calls
- `old()` for form data
- `route()` with current parameters like `route()->is(...)`
- URL helpers that depend on current request

**Session & State:**
- `session()` calls
- Flash messages
- `$errors` variable
- CSRF tokens (`@csrf`, `csrf_token()`)

**Time-Dependent:**
- `now()`, `today()`, `Carbon::now()`
- Any content that changes based on current time

**Laravel Directives:**
- `@error`, `@enderror`
- `@method`
- Any directive that depends on request context

**Component Dependencies:**
- Components that inherit parent props (except when using `@aware`)

**Pagination:**
- `$paginator->links()`, `$paginator->render()`
- Any pagination-related methods or properties
- Components that display pagination controls
- Data tables with pagination

**Nested Non-Foldable Components:**
- Components that contain other components which use runtime data
- Parent components can't be `@blaze` if any child component is dynamic
- Watch for `<x-*>` tags inside the component that might be non-foldable

### 4. Analysis Process

When analyzing a component:

1. **Scan for unsafe patterns** using the lists above
2. **Check for child components** - look for any `<x-*>` tags and verify they are also foldable
3. **Check for indirect dependencies** - props that might contain dynamic data (like paginator objects)
4. **Consider context** - how the component is typically used
5. **Test edge cases** - what happens with different prop values

#### Special Case: Nested Components

When a component directly renders other Blade components in its template (not via slots), verify those are also foldable:

```blade
{{-- Parent component --}}
@blaze <!-- ⚠️ Only safe if directly rendered child components are foldable -->

<div class="data-table">
    <x-table-header /> <!-- Must be foldable -->
    {{ $slot }} <!-- ✅ Slot content is handled separately, can be dynamic -->
    <x-table-footer /> <!-- Must be foldable -->
    <x-table-pagination /> <!-- ❌ If this uses paginator, parent can't be @blaze -->
</div>
```

**Key distinction**:
- Components **hardcoded in the template** must be foldable for the parent to be @blaze
- Content **passed through slots** is handled separately and can be dynamic

### 5. Making Recommendations

**For safe components:**
```
This component is safe for @blaze because it only renders static HTML and passed props. Add @blaze at the top of the file.
```

**For unsafe components:**
```
This component cannot use @blaze because it contains [specific pattern]. The [pattern] changes at runtime and would be frozen at compile-time, causing incorrect behavior.
```

**For borderline cases:**
```
This component might be safe for @blaze, but consider if [specific concern]. Test thoroughly after adding @blaze to ensure it behaves correctly across different requests. If folding isn't possible, memoization will still provide performance benefits.
```

## Common User Requests

### "Can I add @blaze(fold: true) to this component?"

1. Read the component file
2. Analyze for unsafe patterns that prevent folding
3. Check if component is always called with static props
4. Provide a clear yes/no with explanation
5. If no, suggest using default `@blaze` (function compilation) instead

### "Add @blaze(fold: true) to my components"

1. Find all component files (`resources/views/components/**/*.blade.php`)
2. Analyze each component individually for folding eligibility
3. Add `@blaze(fold: true)` only to components that meet strict requirements
4. For other components, recommend default `@blaze` instead
5. Report which components use folding, function compilation, or neither

### "Optimize my Blade components"

1. Recommend adding default `@blaze` (function compilation) to all components
2. Identify rare candidates for `@blaze(fold: true)` (always called with static props)
3. Suggest architectural improvements if needed
4. Explain that function compilation (94-97% faster) is sufficient for most cases

## Implementation Guidelines

### Adding @blaze(fold: true) to Components

When adding `@blaze(fold: true)` to a component:

1. **Always read the component first** to verify it meets folding requirements
2. **Add @blaze(fold: true) as the very first line** of the component file
3. **Include a line break after the directive**
4. **Preserve existing formatting** and structure
5. **Don't modify component logic** unless specifically requested

Example edit:
```blade
{{-- Before --}}
@props(['title'])

<h1>{{ $title }}</h1>

{{-- After --}}
@blaze(fold: true)

@props(['title'])

<h1>{{ $title }}</h1>
```

**Important:** Only add `@blaze(fold: true)` if the component meets ALL folding requirements. For most components, recommend default `@blaze` instead.

### Batch Operations

When processing multiple components:

1. **Process files individually** - don't batch edits
2. **Report results clearly** - which succeeded, which failed, and why
3. **Provide summary statistics** - "Added @blaze to 15 of 23 components"
4. **List problematic components** with specific reasons for skipping

### Error Handling

If Blaze detects unsafe patterns in a `@blaze` component, it will show compilation errors. When helping users:

1. **Explain the error** in simple terms
2. **Show the problematic code** and why it's unsafe
3. **Suggest solutions** - remove @blaze or refactor the component
4. **Provide alternatives** if the optimization is important

## Testing Recommendations

After adding `@blaze` to components:

1. **Test with different props** to ensure consistent rendering
2. **Verify in different contexts** - authenticated vs guest users
3. **Check edge cases** - empty props, unusual values
4. **Monitor for compilation errors** when views are first accessed

## Performance Considerations

- **Start with simple components** - buttons, cards, badges
- **Focus on frequently used components** for maximum impact
- **Avoid premature optimization** - profile first to identify bottlenecks
- **Monitor compilation time** - too many complex optimizations can slow builds
