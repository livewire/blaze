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
    Click me
</x-button>
```

Blaze will automatically optimize it during compilation, pre-rendering the static parts while preserving dynamic content.

### Important Notes

- Only mark components as `@pure` if they don't have runtime dependencies (like `request()`, `auth()`, `session()`, etc.)
- Components with `@aware` directives cannot be optimized
- If a component can't be safely optimized, Blaze will automatically fall back to normal Blade rendering

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
