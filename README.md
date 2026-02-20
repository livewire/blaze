# 🔥 Blaze

Speed up your Laravel app with optimized Blade component rendering.

```
Rendering 25,000 anonymous components:

Without Blaze  ████████████████████████████████████████  500ms
With Blaze     █                                          13ms
```

# Introduction

Out of the box, Blaze is a **drop-in replacement** for anonymous Blade components that requires no changes to your existing code. 

It works by compiling your templates into optimized PHP functions instead of using the standard rendering pipeline — eliminating 91-97% of the overhead while maintaining near full feature parity with Blade.

For even greater performance, Blaze offers two additional strategies. These require extra configuration and careful consideration:
- **Memoization**: caching for repeated renders
- **Folding**: pre-rendering into static HTML


# Installation

You may install Blaze via Composer:

```bash
composer require livewire/blaze:^1.0@beta
```

> [!TIP]
> If you're using [Flux UI](https://fluxui.dev), simply install Blaze and you're ready to go — no configuration needed!

# Getting started

There are two ways to enable Blaze:

**A) Add the `@blaze` directive** to individual components — great for trying it out or enabling Blaze on specific templates.

**B) Optimize entire directories** from your service provider — ideal for optimizing many components at once.

After enabling Blaze with either approach, clear your compiled views:

```bash
php artisan view:clear
```

## Option A: The `@blaze` directive

Add `@blaze` to the top of any anonymous component to enable Blaze for that template:

```blade
@blaze

<button {{ $attributes }}>
    {{ $slot }}
</button>
```

Strategies may be specified as arguments:

```blade
@blaze(memo: true)

@blaze(fold: true)
```

## Option B: Optimize directories

Call `Blaze::optimize()` in your `AppServiceProvider` to enable Blaze for entire directories at once:

```php
use Livewire\Blaze\Blaze;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Blaze::optimize()->in(resource_path('views/components'));

    // ...
}
```

We recommend starting with specific directories, as your app may rely on features Blaze doesn't support. Gradually expand coverage and verify compatibility with [known limitations](#limitations).

```php
Blaze::optimize()
    ->in(resource_path('views/components/button'))
    ->in(resource_path('views/components/modal'));
```

To exclude a subdirectory, use `compile: false`.

```php
Blaze::optimize()
    ->in(resource_path('views/components'))
    ->in(resource_path('views/components/legacy'), compile: false);
```

You may also enable different optimization strategies per folder.

```php
Blaze::optimize()
    ->in(resource_path('views/components/icons'), memo: true)
    ->in(resource_path('views/components/cards'), fold: true);
```

Component-level `@blaze` directives override directory-level settings.


# Limitations

Blaze supports all essential features and produces HTML output identical to Blade. While the focus is on maximizing performance with full compatibility, there are some limitations to be aware of:

- **Class-based components** are not supported
- **The `$component` variable** is not available
- **View composers / creators / lifecycle events** do not fire
- **Auto-injecting `View::share()` variables** is not supported

    Access shared data manually using `$__env->shared('key')`
- **Cross boundary `@aware`** between Blade and Blaze

    Both parent and child must use Blaze for values to propagate
- **Rendering Blaze components using `view()`** will not work

    Blaze components can only be rendered using the component tag

# Optimization Strategies

By default, Blaze uses the **Function Compiler**. It works for virtually all components and provides significant performance improvements — sufficient for most use cases.

For even greater gains, you may consider the advanced strategies below. These require additional thought and care.

| Strategy | Parameter | Default | Best For |
|----------|-----------|----------|----------|
| [Function Compiler](#function-compiler) | `compile` | `true` | General use |
| [Runtime Memoization](#runtime-memoization) | `memo` | `false` | Repeated components |
| [Compile-Time Folding](#compile-time-folding) | `fold` | `false` | Maximum performance |


# Function Compiler

This is the default behavior. It's a reliable optimization that requires no changes and can be safely applied to nearly all templates without concerns about stale data or dynamic content.

Rendering 25,000 anonymous components in a loop:

| Scenario | Blade | Blaze | Reduction |
|----------|-------|-------|-----------|
| No attributes | 500ms | 13ms | 97.4% |
| Attributes only | 457ms | 26ms | 94.3% |
| Attributes + merge() | 546ms | 44ms | 91.9% |
| Props + attributes | 780ms | 40ms | 94.9% |
| Default slot | 460ms | 22ms | 95.1% |
| Named slots | 696ms | 49ms | 93.0% |
| @aware (nested) | 1,787ms | 129ms | 92.8% |

> These numbers reflect rendering pipeline overhead. If your templates perform expensive operations internally, that work will still affect performance.

## How it works

When you enable Blaze, your templates are compiled into optimized PHP functions that skip the standard rendering pipeline while maintaining compatibility with Blade syntax.

```blade
@blaze 

@props(['type' => 'button'])

<button {{ $attributes->merge(['type' => $type]) }}>
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

When you include the component, Blaze calls this function directly.

```blade
<x-button type="submit">Send</x-button>
```

Becomes:

```php
_c4f8e2a1(['type' => 'submit'], ['default' => 'Send']);
```

# Runtime Memoization

This strategy is ideal for icons, avatars, and other elements that frequently appear with the same props. When a memoized component appears multiple times on a page, it renders only once.

> [!IMPORTANT]
> Memoization only works on components without slots.

## How it works

The output is cached based on the component name and props passed to it.

```blade
@blaze(memo: true)

@props(['name'])

<x-dynamic-component :component="'icon-' . $name" />
```

When you include the component, Blaze wraps it in a cache check and only renders it the first time it's used with those props.

```blade
<x-icon :name="$task->status->icon" />
```

Becomes:

```blade
<?php $key = Memo::key('icon', ['name' => $task->status->icon]); ?>

<?php if (! Memo::has($key)): ?>
    <!-- Render and store into cache: -->
    <x-icon :name="$task->status->icon">
<?php endif; ?>

<?php echo Memo::get($key); ?>
```

# Compile-Time Folding

Compile-time folding is Blaze's most aggressive optimization. The component ceases to exist at runtime — no function call, no variable resolution, no overhead whatsoever. Just the HTML.

Rendering time remains constant regardless of component count:

| Components | Blade | Blaze (folded) |
|------------|-------|----------------|
| 25,000 | 500ms | 0.68ms |
| 50,000 | 1,000ms | 0.68ms |
| 100,000 | 2,000ms | 0.68ms |

> [!CAUTION]
> **Folding requires careful consideration**. Used incorrectly, it can cause subtle bugs that are difficult to diagnose. Make sure you fully understand how it works before enabling this strategy.

## How It Works

Blaze pre-renders components during compilation, embedding the HTML directly into your template. This eliminates all runtime overhead, enabling exceptional performance gains. However, these gains come at a cost — folding requires a thorough understanding of its mechanics and careful consideration.

The following sections explore how folding works so you can use it safely.

### Overview

The most important thing to understand is that folding produces static HTML. All internal logic, conditions, and dynamic content are baked in at compile time. This can create subtle bugs where a component works correctly in some places but breaks in others.

Blaze tries to avoid folding when it's likely to cause problems, but it cannot detect all cases. You'll need to analyze each component individually and configure when folding should be aborted.

### Global state

**Components that use global state should never be folded**. This includes anything not passed in from the outside — data accessed via helper functions, facades, or Blade directives. Using any of these patterns inside the component will produce incorrect results when folded.

> [!WARNING]
> Components that use global state must not be marked with `fold: true`. Blaze attempts to detect global state usage and will throw an exception when it does, but it cannot catch everything.

| Category | Examples |
|----------|----------|
| Database | `User::get()` |
| Authentication | `auth()->check()`, `@auth`, `@guest` |
| Session | `session('key')` |
| Request | `request()->path()`, `request()->is()` |
| Validation | `$errors->has()`, `$errors->first()` |
| Time | `now()`, `Carbon::now()` |
| Security | `@csrf` |

> This applies to internal logic. Passing global state into the component via attributes or slots can be fine — however, there are exceptions. We'll explore these in the following sections.

### Static attributes 

Let's explore the folding process with a simple example where everything works smoothly.

Consider a button component that dynamically resolves Tailwind classes based on the color prop.

```blade
@blaze(fold: true)

@props(['color'])

@php
$classes = match($color) {
    'red' => 'bg-red-500 hover:bg-red-400',
    'blue' => 'bg-blue-500 hover:bg-blue-400',
    default => 'bg-gray-500 hover:bg-gray-400',
};
@endphp

<button {{ $attributes->class($classes) }}>
    {{ $slot }}
</button>
```

When you include it with a static prop:

```blade
<x-button color="red">Submit</x-button>
```

Blaze renders the component and replaces it with static HTML during compilation:

```blade
<button class="bg-red-500 hover:bg-red-400">
    Submit
</button>
```

The component only needed to be rendered once — subsequent requests simply serve the static HTML. All dynamic parts have been folded away, and the `color="red"` prop has been transformed into a static class name.

This worked because all props were **static**. They never change, so Blaze can safely remove all dynamic parts while still producing the correct output.

### Dynamic pass-through attributes

The next example illustrates a scenario with dynamic attributes where folding still works correctly.

Even though Blaze doesn't have access to runtime data, it works around this by replacing dynamic attributes with placeholders and substituting the original expressions back into the final output.

Let's assign a random id to the button:

```blade
<x-button color="red" :id="Str::random()">Submit</x-button>
```

During compilation, Blaze identifies dynamic attributes and stores their values:

| Placeholder | Dynamic value |
|------|--------|
| ATTR_PLACEHOLDER_1 | `Str::random()`

Next, it pre-renders the component using the placeholder:

```blade
<x-button color="red" id="ATTR_PLACEHOLDER_1">Submit</x-button>
```

Which results in:

```blade
<button class="bg-red-500 hover:bg-red-400" id="ATTR_PLACEHOLDER_1">
    Submit
</button>
```

Finally, Blaze substitutes the original expression back into the HTML:

```blade
<button class="bg-red-500 hover:bg-red-400" id="{{ Str::random() }}">
    Submit
</button>
```

This worked because `id` is a **pass-through attribute** — it's output directly without transformation or use in internal logic.

### Dynamic non-pass-through attributes

The next example illustrates a scenario with dynamic attributes where folding breaks.

**Blaze automatically aborts folding** when a component receives a dynamic attribute that is also defined in `@props`. It falls back to function compilation and the performance benefits of folding are not realized.

> Blaze assumes that attributes defined in @props are likely used in internal logic and therefore should not be folded. This defensive behavior avoids most common errors. However, there are cases where this is too restrictive, which we'll explore in [Selective folding](#selective-folding).

To understand why folding breaks, let's see what would happen if it weren't aborted.

Pass a dynamic attribute to the `color` prop:

```blade
<x-button :color="$deleting ? 'red' : 'blue'" />
```

Blaze creates the mapping table and pre-renders with a placeholder:

| Placeholder | Dynamic value |
|------|--------|
| ATTR_PLACEHOLDER_1 | `$deleting ? 'red' : 'blue'` |

```blade
<x-button color="ATTR_PLACEHOLDER_1">
```

Here's where the problem occurs. Inside the component, `$color` now evaluates to the literal string `"ATTR_PLACEHOLDER_1"`:

```blade
@php
$classes = match("ATTR_PLACEHOLDER_1") {
    'red' => 'bg-red-500 hover:bg-red-400',
    'blue' => 'bg-blue-500 hover:bg-blue-400',
    default => 'bg-gray-500 hover:bg-gray-400',
};
@endphp
```

The match lookup fails and falls back to the default — the button is always gray:

```blade
<button class="bg-gray-500 hover:bg-gray-400 ❌" type="button">
    Submit
</button>
```

### Slots

Slots are handled similarly to attributes — replaced with placeholders during pre-rendering and restored afterwards. Unlike attributes, slots are always considered **pass-through** and will never abort folding unless marked as `unsafe` (see [Selective folding](#selective-folding)).

```blade
<x-button>{{ $action }}</x-button>
```

During compilation, Blaze stores slot values:

| Placeholder | Dynamic value |
|------|--------|
| SLOT_PLACEHOLDER_1 | `{{ $action }}` |

Blaze pre-renders with the placeholder:

```blade
<x-button>SLOT_PLACEHOLDER_1</x-button>
```

Which results in:

```blade
<button class="bg-gray-500 hover:bg-gray-400">
    SLOT_PLACEHOLDER_1
</button>
```

Finally, Blaze substitutes the original expression back into the HTML:

```blade
<button class="bg-gray-500 hover:bg-gray-400">
    {{ $action }}
</button>
```


## Selective folding

By default, Blaze errs on the side of caution and aborts folding when dynamic values are detected in attributes defined in `@props`. This prevents many common mistakes but can be overly restrictive. Similarly, slots are always considered pass-through, which may not suit every case.

You may fine-tune this behavior using the `safe` and `unsafe` parameters of the `@blaze` directive.

### Using `safe`

Use `safe` to mark props that are **pass-through** — they're not transformed or used in internal logic. This allows folding to proceed even when those props are dynamic.

```blade
@blaze(fold: true, safe: ['level'])

@props(['level' => 1])

<h{{ $level }}>{{ $slot }}</h{{ $level }}>
```

```blade
<x-heading :level="$isFeaturedSection ? 1 : 2" />
```

The component now folds successfully, producing:

```blade
<h{{ $isFeaturedSection ? 1 : 2 }}></h{{ $isFeaturedSection ? 1 : 2 }}>
```

### Using `unsafe` for slots

Use `unsafe` to mark slots that are **not** pass-through — they're transformed or used in internal logic. This forces folding to abort.

```blade
@blaze(fold: true, unsafe: ['slot'])

@if ($slot->hasActualContent())
    <span>No results</span>
@else
    <div>{{ $slot }}</div>
@endif
```

```blade
<x-items>
    @if(isPro())
        ...
    @endif
</x-items>
```

This also works with named slots:

```blade
@blaze(fold: true, unsafe: ['footer'])

<div>
    @if($footer->hasActualContent())
        ...
    @endif
</div>
```

The component now correctly aborts folding whenever it receives a slot.

> [!IMPORTANT]
> Blaze does not inspect slot content to determine if it's dynamic or static. When a slot is marked as `unsafe`, folding is always aborted regardless of the actual content. Self-closing components will still fold successfully.

### Using `unsafe` for attributes

You may also mark attributes that are not defined in `@props` as unsafe.

This prevents folding errors when **non-pass-through** attributes are resolved dynamically via `$attributes`:

```blade
@blaze(fold: true, unsafe: ['href'])

@php
$active = $attributes->get('href') === url()->current();
@endphp

<a {{ $attributes->merge(['data-active' => $active]) }}>
    {{ $slot }}
</a>
```

You may also mark the entire attribute bag as unsafe:

```blade
@blaze(fold: true, unsafe: ['attributes'])

@php
$active = $attributes->get('href') === url()->current();
$external = $attributes->get('target') === '_blank';
@endphp

<a {{ $attributes->merge(['data-active' => $active]) }}>
    @if($external)
        ...
    @endif
</a>
```

Any dynamic attributes will now cause folding to abort.

## The Unblaze Directive

When a component is mostly foldable but contains a dynamic section, use `@unblaze` to exclude that section:

```blade
@blaze(fold: true)

@props(['name', 'label'])

<div>
    <label>{{ $label }}</label>
    <input name="{{ $name }}">

    @unblaze(scope: ['name' => $name])
        @if($errors->has($scope['name']))
            {{ $errors->first($scope['name']) }}
        @endif
    @endunblaze
</div>
```

Variables from the component scope must be passed explicitly using the `scope` parameter.

# Reference

### Directive Parameters

```blade
@blaze(fold: true, safe: ['title'], unsafe: ['attributes'])
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `fold` | `bool` | `false` | Enable compile-time folding |
| `memo` | `bool` | `false` | Enable runtime memoization |
| `safe` | `array` | `[]` | Props that may fold even when dynamic |
| `unsafe` | `array` | `[]` | Props that should abort folding when dynamic |

**Accepted values for `safe` and `unsafe`:**

| Value | Target |
|-------|--------|
| `*` | All props / attributes / slots |
| `slot` | The default slot |
| `[name]` | A property / attribute / slot |
| `attributes` | Attributes not defined in `@props` |


### Directory Configuration

```php
Blaze::optimize()
    ->in(resource_path('views/components'))
    ->in(resource_path('views/components/ui'), fold: true)
    ->in(resource_path('views/components/icons'), memo: true)
    ->in(resource_path('views/components/legacy'), compile: false);
```

| Option | Default | Description |
|--------|---------|-------------|
| `compile` | `true` | Enable Blaze compilation. Set `false` to exclude. |
| `fold` | `false` | Enable compile-time folding |
| `memo` | `false` | Enable runtime memoization |

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
