# Laravel Blaze - Agent Guidance

This document provides guidance for AI assistants helping users work with Laravel Blaze, a package that optimizes Blade component rendering performance through compile-time code folding.

## Overview

Laravel Blaze is a performance optimization package that pre-renders static portions of Blade components at compile-time, dramatically reducing runtime overhead. It works by:

1. Identifying components marked with the `@blaze` directive in their source
2. Analyzing component source for runtime dependencies
3. Pre-rendering eligible components during Blade compilation
4. Falling back to normal rendering for unsafe components

## Core Concepts

### The @blaze Directive

The `@blaze` directive tells Blaze that a component has no runtime dependencies and can be safely optimized. It must be placed at the top of a component file:

```blade
@blaze

@props(['title'])

<h1 class="text-2xl font-bold">{{ $title }}</h1>
```

### Code Folding Process

When a `@blaze` component is encountered, Blaze:
1. Replaces dynamic content being passed in via attributes or slots with placeholders
2. Renders the component with placeholders
3. Validates that placeholders are preserved
4. Replaces placeholders with original dynamic content
5. Outputs the optimized HTML directly into the parent template

## Helping Users Analyze Components

When a user asks about adding `@blaze` to a component or wants you to analyze their components, follow this process:

### 1. Read and Analyze the Component

First, examine the component source code for:
- Runtime dependencies (see unsafe patterns below)
- Dynamic content that changes per request
- Dependencies on global state or context

### 2. Safe Patterns for @blaze

Components are safe for `@blaze` when they only:
- Accept props and render them consistently
- Perform simple data formatting (dates, strings, etc.)
- Render slots without modification

Examples:
```blade
{{-- UI components --}}
@blaze
<div class="card p-4 bg-white rounded shadow">{{ $slot }}</div>

{{-- Prop-based styling --}}
@blaze
@props(['variant' => 'primary'])
<button class="btn btn-{{ $variant }}">{{ $slot }}</button>

{{-- Simple formatting --}}
@blaze
@props(['price'])
<span class="font-mono">${{ number_format($price, 2) }}</span>
```

### 3. Unsafe Patterns (Never @blaze)

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
- `@aware` directive
- Components that inherit parent props

**Pagination:**
- `$paginator->links()`, `$paginator->render()`
- Any pagination-related methods or properties
- Components that display pagination controls
- Data tables with pagination

**Nested Non-Pure Components:**
- Components that contain other components which use runtime data
- Parent components can't be `@blaze` if any child component is dynamic
- Watch for `<x-*>` tags inside the component that might be non-pure

### 4. Analysis Process

When analyzing a component:

1. **Scan for unsafe patterns** using the lists above
2. **Check for child components** - look for any `<x-*>` tags and verify they are also pure
3. **Check for indirect dependencies** - props that might contain dynamic data (like paginator objects)
4. **Consider context** - how the component is typically used
5. **Test edge cases** - what happens with different prop values

#### Special Case: Nested Components

When a component directly renders other Blade components in its template (not via slots), verify those are also pure:

```blade
{{-- Parent component --}}
@blaze <!-- ⚠️ Only safe if directly rendered child components are pure -->

<div class="data-table">
    <x-table-header /> <!-- Must be pure -->
    {{ $slot }} <!-- ✅ Slot content is handled separately, can be dynamic -->
    <x-table-footer /> <!-- Must be pure -->
    <x-table-pagination /> <!-- ❌ If this uses paginator, parent can't be @blaze -->
</div>
```

**Key distinction**: 
- Components **hardcoded in the template** must be pure for the parent to be @blaze
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
This component might be safe for @blaze, but consider if [specific concern]. Test thoroughly after adding @blaze to ensure it behaves correctly across different requests.
```

## Common User Requests

### "Can I add @blaze to this component?"

1. Read the component file
2. Analyze for unsafe patterns
3. Provide a clear yes/no with explanation
4. If no, suggest alternatives or modifications

### "Add @blaze to my components"

1. Find all component files (`resources/views/components/**/*.blade.php`)
2. Analyze each component individually
3. Add `@blaze` only to safe components (include a line break after `@blaze` )
4. Report which components were modified and which were skipped with reasons

### "Optimize my Blade components"

1. Audit existing components for @blaze eligibility
2. Identify components that could be refactored to be pure
3. Suggest architectural improvements for better optimization
4. Provide before/after examples

## Implementation Guidelines

### Adding @blaze to Components

When adding `@blaze` to a component:

1. **Always read the component first** to understand its structure
2. **Add @blaze as the very first line** of the component file
3. **Preserve existing formatting** and structure
4. **Don't modify component logic** unless specifically requested

Example edit:
```blade
{{-- Before --}}
@props(['title'])

<h1>{{ $title }}</h1>

{{-- After --}}
@blaze

@props(['title'])

<h1>{{ $title }}</h1>
```

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