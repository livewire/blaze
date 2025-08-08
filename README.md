# 🔥 Blaze

Speed up your Laravel app by optimizing Blade component rendering performance.

## Introduction

Blaze is a Laravel package that dramatically improves the rendering performance of your Blade components through compile-time optimization. It identifies static portions of your templates and pre-renders them, removing much of Blade's runtime overhead.

## Installation

You can install the package via composer:

```bash
composer require livewire/blaze
```

## Usage

To optimize a Blade component for performance, simply add the `@pure` directive at the top of your component file:

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

## When to Use @pure

The `@pure` directive tells Blaze that a component has no runtime dependencies and can be safely optimized. Only add it to components that render the same way every time they're compiled.

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

```blade
{{-- Simple formatting components --}}
@pure

@props(['date'])

<time datetime="{{ $date->toISOString() }}">
    {{ $date->format('M j, Y') }}
</time>
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

### 🔍 Watch out for

Be careful with these patterns that might seem safe but can cause issues:

```blade
{{-- Time-dependent content --}}
<p>Generated on {{ now() }}</p> <!-- Changes every request -->

{{-- User-specific content --}}
<p>Hello {{ auth()->user()->name }}</p> <!-- Different per user -->

{{-- Environment-dependent values --}}
<script src="{{ config('app.cdn_url') }}/app.js"></script> <!-- Might change -->
```

### 💡 Pro Tips

- **Start with simple components**: Begin with basic UI components like buttons, cards, and badges
- **Check your dependencies**: If your component uses any Laravel helpers or global variables, think twice
- **Test thoroughly**: After adding `@pure`, verify the component still works correctly across different requests
- **Blaze is forgiving**: If a component can't be optimized, Blaze will automatically fall back to normal rendering

### Error Detection

When you add `@pure` to a component with runtime dependencies, Blaze will detect common unsafe patterns and show helpful error messages during compilation. This prevents broken components and guides you toward the correct implementation.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
