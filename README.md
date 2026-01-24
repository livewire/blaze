# 🔥 Blaze

```
Rendering 25,000 anonymous components:

Without Blaze  ████████████████████████████████████████  500ms
With Blaze     █                                          13ms
```

## Introduction

Blaze supercharges your Blade components by compiling them into direct PHP function calls, eliminating 91-97% of the rendering overhead. No configuration changes, no template modifications - just faster components.

Blaze is designed as a drop-in replacement for anonymous Blade components. It supports all essential features including props, slots, attributes, and `@aware`. The HTML output is identical to standard Blade rendering.

> [!IMPORTANT]
> When using `@aware`, both the parent and child components must use Blaze for values to propagate correctly.

## Installation

You may install Blaze via Composer:

```bash
composer require livewire/blaze
```

> [!TIP]
> If you're using Flux, just install Blaze and you're good to go!

### Enabling Blaze

Enable Blaze in your `AppServiceProvider`:

```php
use Livewire\Blaze\Blaze;

public function boot(): void
{
    Blaze::optimize();
}
```

This optimizes all [anonymous component paths](https://laravel.com/docs/12.x/blade#anonymous-component-paths). After enabling Blaze, you should clear your compiled views:

```bash
php artisan view:clear
```

> [!NOTE]
> Blaze focuses on anonymous components. Class-based components, the `$component` variable, `View::share()` variables, view composers, and component lifecycle events are not supported.

## Configuration

### Directory-Based Optimization

You may scope Blaze to specific directories:

```php
Blaze::optimize()
    ->in(resource_path('views/components/icons'))
    ->in(resource_path('views/components/ui'));
```

Different optimization strategies can be enabled per directory:

```php
Blaze::optimize()
    ->in(resource_path('views/components/icons'), memo: true)
    ->in(resource_path('views/components/cards'), fold: true);
```

### Per-Component Optimization

You may also enable Blaze on individual components using the `@blaze` directive:

```blade
@blaze

<button {{ $attributes }}>
    {{ $slot }}
</button>
```

Optimization strategies can be specified directly:

```blade
@blaze(memo: true)

@blaze(fold: true)
```

Component-level directives override directory-level settings.

## Optimization Strategies

Blaze offers three optimization strategies, each suited to different use cases:

| Strategy | Parameter | Best For | Overhead Reduction |
|----------|-----------|----------|-------------------|
| Function Compilation | (default) | General use | 91-97% |
| Runtime Memoization | `memo` | Repeated identical components | 91-97% + deduplication |
| Compile-Time Folding | `fold` | Maximum performance | ~100% |

### Function Compilation

Function compilation is the default strategy. It transforms your components into optimized PHP functions, bypassing the entire component rendering pipeline while maintaining identical behavior to standard Blade.

#### Benchmark Results

The following benchmarks represent 25,000 components rendered in a loop:

| Scenario | Blade | Blaze | Reduction |
|----------|-------|-------|-----------|
| No attributes | 500ms | 13ms | 97.4% |
| Attributes only | 457ms | 26ms | 94.3% |
| Attributes + merge() | 546ms | 44ms | 91.9% |
| Props + attributes | 780ms | 40ms | 94.9% |
| Default slot | 460ms | 22ms | 95.1% |
| Named slots | 696ms | 49ms | 93.0% |
| @aware (nested) | 1,787ms | 129ms | 92.8% |

These numbers reflect rendering pipeline overhead. If your components execute expensive operations internally, that work will still affect performance.

#### How It Works

When you enable Blaze, components are compiled into direct function calls:

```blade
@blaze

@props(['type' => 'button'])

<button type="{{ $type }}" class="inline-flex">
    {{ $slot }}
</button>
```

Compiles to:

```php
function _c4f8e2a1($__data, $__slots) {
    $type = $__data['type'] ?? 'button';
    $attributes = new BlazeAttributeBag($__data);
    // ...
}
```

When you include the component, Blaze calls the function directly instead of resolving through Blade's rendering pipeline:

```blade
<x-button type="submit">Send</x-button>
```

Becomes:

```php
_c4f8e2a1(['type' => 'submit'], ['default' => 'Send']);
```

### Runtime Memoization

Runtime memoization caches component output during a single request. When a component renders with the same props multiple times, it only executes once.

> [!NOTE]
> Memoization only works for components without slots.

This strategy is ideal for components like icons and avatars that appear many times with identical values:

```blade
@blaze(memo: true)

@props(['name'])

<x-dynamic-component :component="'icons.' . $name" />
```

If `<x-icon name="check" />` appears 50 times on a page, it renders once and reuses the output.

### Compile-Time Folding

Compile-time folding is Blaze's most aggressive optimization. It pre-renders components during compilation, embedding the HTML directly into your template. The component no longer exists at runtime.

> [!CAUTION]
> Folding requires careful consideration. Used incorrectly, it can cause subtle bugs that are difficult to diagnose. Review the [Folding](#folding) section before enabling.

```blade
@blaze(fold: true)

@props(['type' => 'button'])

<button type="{{ $type }}" class="inline-flex">
    {{ $slot }}
</button>
```

When compiled:

```blade
<x-button type="submit">Submit</x-button>
```

Becomes:

```blade
<button type="submit" class="inline-flex">
    Submit
</button>
```

## Folding

This section covers the intricacies of compile-time folding. If you're using the default function compilation strategy, you can skip this section entirely.

### How Folding Works

When a component is folded, Blaze renders it at compile-time and embeds the output directly into your template. This eliminates virtually all runtime overhead but introduces constraints on what the component can do.

### Dynamic Attributes

Blaze handles dynamic attributes that pass through `$attributes` unchanged using placeholder replacement:

```blade
@blaze(fold: true)

<div {{ $attributes->class(['rounded-lg shadow-md p-6']) }}>
    {{ $slot }}
</div>
```

When called:

```blade
<x-card :class="$featured ? 'bg-yellow-50' : 'bg-white'">
    Content
</x-card>
```

Blaze replaces the dynamic value with a placeholder, renders the component, then substitutes the original expression back:

| Step | Result |
|------|--------|
| Placeholder | `<x-card class="__BLAZE_PH_1__">` |
| Render | `<div class="rounded-lg shadow-md p-6 __BLAZE_PH_1__">` |
| Replace | `<div class="... <?php echo e($featured ? '...' : '...'); ?>">` |

### Props

Problems occur when dynamic values are captured by `@props` and used in logic:

```blade
@blaze(fold: true)

@props(['status'])

@php
    $colors = [
        'active' => 'bg-green-100',
        'pending' => 'bg-yellow-100',
    ];
@endphp

<span class="{{ $colors[$status] ?? 'bg-gray-100' }}">
    {{ $status }}
</span>
```

When called with a dynamic prop:

```blade
<x-badge :status="$user->status" />
```

The array lookup executes with the placeholder string `"__BLAZE_PH_1__"`, not the actual value. The lookup fails and falls back to the default.

**Blaze automatically aborts folding when a defined prop receives a dynamic value.**

### The Safe Parameter

If a prop passes through to the output without being used in conditions or transformations, you may mark it as safe:

```blade
@blaze(fold: true, safe: ['title'])

@props(['title', 'level' => 2])

<h{{ $level }} class="font-bold">
    {{ $title }}
</h{{ $level }}>
```

Now dynamic titles fold successfully:

```blade
<x-heading :title="$post->title" />
```

> [!WARNING]
> Only mark props as safe if they truly pass through unchanged. Using a "safe" prop in conditions or transformations will cause bugs.

To mark all props as safe:

```blade
@blaze(fold: true, safe: ['*'])
```

### The Unsafe Parameter

When certain dynamic values would break a component, mark them as unsafe to abort folding:

```blade
@blaze(fold: true, unsafe: ['attributes'])

@php
    $icon = match($attributes->get('variant')) {
        'success' => 'check-circle',
        'error' => 'x-circle',
        default => 'info-circle',
    };
@endphp

<div {{ $attributes->except('variant') }}>
    <x-icon :name="$icon" />
    {{ $slot }}
</div>
```

With `unsafe: ['attributes']`, any dynamic attribute causes folding to abort, falling back to function compilation.

For more precision, target specific attributes:

```blade
@blaze(fold: true, unsafe: ['variant'])
```

Now `<x-alert :variant="$type">` aborts folding, but `<x-alert :class="$classes">` still folds.

### Slots

Slots are considered safe by default - their content passes through unchanged. However, slot inspection methods won't work correctly:

```blade
{{-- This won't work when folded --}}
@if($slot->isNotEmpty())
    <div>{{ $slot }}</div>
@endif
```

The placeholder is never empty, so the condition always evaluates to `true`.

To inspect slot content, mark the slot as unsafe:

```blade
@blaze(fold: true, unsafe: ['slot'])

@if($slot->isNotEmpty())
    <div>{{ $slot }}</div>
@endif
```

For named slots:

```blade
@blaze(fold: true, unsafe: ['footer'])

@if($footer->isNotEmpty())
    <footer>{{ $footer }}</footer>
@endif
```

### Global State

Folded components are rendered at compile-time. Any global state is captured at compilation, not runtime:

```blade
@blaze(fold: true)

@if(auth()->check())
    <span>Welcome, {{ auth()->user()->name }}</span>
@else
    <span>Please log in</span>
@endif
```

If a logged-in user triggers compilation, the "Welcome" message gets permanently embedded. All subsequent users see the same content.

**Components that read global state should not be folded.** Common sources of global state:

| Category | Examples |
|----------|----------|
| Authentication | `auth()->check()`, `@auth`, `@guest` |
| Session | `session('key')` |
| Request | `request()->path()`, `request()->is()` |
| Validation | `$errors->has()`, `$errors->first()` |
| Time | `now()`, `Carbon::now()` |
| Security | `@csrf` |

### The Unblaze Directive

When a component is mostly foldable but needs a dynamic section, use `@unblaze` to exclude that section:

```blade
@blaze(fold: true)

@props(['name', 'label'])

<div class="form-group">
    <label>{{ $label }}</label>
    <input name="{{ $name }}" class="rounded-md border-gray-300">

    @unblaze(scope: ['name' => $name])
        @error($scope['name'])
            <p class="text-red-600">{{ $message }}</p>
        @enderror
    @endunblaze
</div>
```

The label and input are folded. Error handling remains dynamic.

Variables from the component scope must be passed explicitly using the `scope` parameter.

## Reference

### Directive Parameters

```blade
@blaze(fold: true, safe: ['title'], unsafe: ['attributes'])
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `fold` | `bool` | `false` | Enable compile-time folding |
| `memo` | `bool` | `false` | Enable runtime memoization |
| `safe` | `array` | `[]` | Props that fold even when dynamic |
| `unsafe` | `array` | `[]` | Values that abort folding when dynamic |

**Special values for `safe` and `unsafe`:**

| Value | Target |
|-------|--------|
| `'*'` | All props |
| `'attributes'` | The entire `$attributes` bag |
| `'[name]'` | A specific prop or attribute by name |
| `'slot'` | The default slot |
| `'[slot_name]'` | A named slot |

### Directory Configuration

```php
Blaze::optimize()
    ->in(resource_path('views/components/ui'), fold: true)
    ->in(resource_path('views/components/icons'), memo: true)
    ->in(resource_path('views/components/dynamic'), compile: false);
```

| Option | Default | Description |
|--------|---------|-------------|
| `compile` | `true` | Enable Blaze. Set `false` to exclude. |
| `fold` | `false` | Enable compile-time folding |
| `memo` | `false` | Enable runtime memoization |

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
