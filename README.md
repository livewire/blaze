# 🔥 Blaze

Speed up your Laravel app by optimizing Blade component rendering performance.

```
Rendering 25,000 anonymous components:

Without Blaze  ████████████████████████████████████████  500ms
With Blaze     █                                          13ms
```

# Introduction

Out of the box, Blaze is a **drop-in replacement** for anonymous Blade components that does not require any changes to your existing component code. 

It works by compiling components into optimized PHP code instead of using the standard rendering pipeline — this eliminates 91-97% of the overhead while maintaining near full feature parity with Blade.

Blaze offers two additional strategies for even greater performance.

These require additional configuration and considerations:
- **Memoization**: a caching strategy for repeated components
- **Folding**: pre-rendering components into static HTML


# Installation

You may install Blaze via Composer:

```bash
composer require livewire/blaze
```

> [!TIP]
> If you're using [Flux UI](https://fluxui.dev), just install Blaze and you're good to go - no configuration needed!

# Getting started

To get started, call `Blaze::optimize()` in your `AppServiceProvider`.

```php
use Livewire\Blaze\Blaze;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Blaze::optimize()->in(resource_path('view/components'));

    // ...
}
```

After enabling, you should clear your compiled views:

```bash
php artisan view:clear
```

## Configuration

**If you're integrating Blaze into an existing application**, it is recommended to only optimize specific directories or components. This allows you to gradually adopt Blaze and verify compatibility with [known limitations](#limitations).

```php
Blaze::optimize()
    ->in(resource_path('views/components/button')
    ->in(resource_path('views/components/modal');
```

To exclude a sub-folder, use `compile: false`.

```php
Blaze::optimize()
    ->in(resource_path('views/components')
    ->in(resource_path('views/components/legacy', compile: false);
```

You can also enable different optimization strategies per folder.

```php
Blaze::optimize()
    ->in(resource_path('views/components/icons'), memo: true)
    ->in(resource_path('views/components/cards'), fold: true);
```

Alternatively, enable Blaze for individual components using the `@blaze` directive:

```blade
@blaze

<button {{ $attributes }}>
    {{ $slot }}
</button>
```

Optimization strategies can be specified in the arguments:

```blade
@blaze(memo: true)

@blaze(fold: true)
```

Component-level directives override directory-level settings.


# Limitations

Blaze supports all essential features and produces HTML output that is identical to Blade. The focus is on maximizing performance while maintaining compatibility. That said, there are some limitations to be aware of:

- **Class-based components** are not supported
- **The `$component` variable** will not be available
- **View composers / creators / lifecycle events** will not fire
- **Auto-injecting `View::share()` variables** is not supported

    You can still access the data manually via the `$__env` variable:

    ```blade
    {{ $__env->shared('key') }}
    ```
- **Cross boundary `@aware`** between Blade and Blaze components

    Both parent and child must use Blaze for values to propagate.

# Optimization Strategies

By default Blaze uses **Function Compiler**, which works for virtually all components and provides significant performance improvements — this is sufficient for most use cases.

To go even further, you can consider the other strategies that require additional considerations.

| Strategy | Parameter | Default | Best For |
|----------|-----------|----------|----------|
| [Function Compiler](#function-compiler) | `compile` | `true` | General use |
| [Runtime Memoization](#runtime-memoization) | `memo` | `false` | Repeated components |
| [Compile-Time Folding](#compile-time-folding) | `fold` | `false` | Maximum performance |


# Function Compiler

This strategy is the default behavior of Blaze. It is a reliable optimization that requires no changes and can be safely applied to nearly all components without concerns about stale data or dynamic content.

Rendering 25,000 components in a loop:

| Scenario | Blade | Blaze | Reduction |
|----------|-------|-------|-----------|
| No attributes | 500ms | 13ms | 97.4% |
| Attributes only | 457ms | 26ms | 94.3% |
| Attributes + merge() | 546ms | 44ms | 91.9% |
| Props + attributes | 780ms | 40ms | 94.9% |
| Default slot | 460ms | 22ms | 95.1% |
| Named slots | 696ms | 49ms | 93.0% |
| @aware (nested) | 1,787ms | 129ms | 92.8% |

> These numbers reflect rendering pipeline overhead. If your components perform expensive operations internally, that work will still affect performance when using this strategy.

## How it works

When you enable Blaze, your components will be compiled into optimized PHP functions that skip the standard rendering pipeline while maintaining compatibility with Blade component syntax.

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

When you include the component, Blaze calls its function directly.

```blade
<x-button type="submit">Send</x-button>
```

Becomes:

```php
_c4f8e2a1(['type' => 'submit'], ['default' => 'Send']);
```

# Runtime Memoization

This strategy is ideal for components like icons and avatars that are often repeated with the same props. If a component appears on a page multiple times, it will only be rendered once.

> [!IMPORTANT]
> Memoization only works on components without slots.

## How it works

When you enable memoization, your component's output will be cached based on the props that are passed into it.

```blade
@blaze(memo: true)

@props(['name'])

<x-dynamic-component :component="'icon-' . $name" />
```

When you include the component on a page, Blaze wraps it in a cache check:

```blade
<x-icon :name="$task->status->icon" />
```

Becomes:

```blade
<?php if ($__cached = Blaze::cached('icon', ['name' => $task->status->icon]): ?>
    <!-- Retrieve the output from cache: -->
    <?php echo e($__cached); ?>
<?php else: ?>
    <!-- Render and store into cache: -->
    <x-icon :name="$task->status->icon">
<?php endif; ?>
```

# Compile-Time Folding

Compile-time folding is Blaze's most aggressive optimization. It pre-renders components during compilation, embedding the HTML directly into your template. The component ceases to exist at runtime - there is no function call, no variable resolution, no overhead whatsoever.

Rendering time remains constant regardless of component count:

| Components | Blade | Blaze (folded) |
|------------|-------|----------------|
| 25,000 | 500ms | 0.68ms |
| 50,000 | 1,000ms | 0.68ms |
| 100,000 | 2,000ms | 0.68ms |

> [!CAUTION]
> **Folding requires careful consideration**. Used incorrectly, it can cause subtle bugs that are difficult to diagnose. Make sure you fully understand its workings before using this strategy.

## How It Works

This section covers the intricacies of compile-time folding.

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

<button class="{{ $classes }}" type="button">
    {{ $slot }}
</button>
```

When included on a page:

```blade
<x-button color="red">Submit</x-button>
```

Becomes:

```blade
<button class="bg-red-500 hover:bg-red-400" type="submit">
    Submit
</button>
```

This works flawlessly because all data needed to render the component is available at compile-time.

### Dynamic pass-through attributes

Because Blaze pre-renderes components during compilation, it doesn't have access to data that will be passed to the components at runtime. It works around this by replacing dynamic attributes with placeholders and substituting the original expressions back into the final output.

To illustrate this, let's assign a random id to the button:

```blade
<x-button color="red" :id="Str::random()">Submit</x-button>
```

During compilation, Blaze analyzes the component to identify dynamic attributes and store their values:

| Placeholder | Dynamic value |
|------|--------|
| ATTR_PLACEHOLDER_1 | `Str::random()`

Next, it pre-renders the component using the placeholder.

```blade
<x-button color="red" :id="ATTR_PLACEHOLDER_1">Submit</x-button>
```

Which results in:

```blade
<button class="bg-red-500 hover:bg-red-400" type="submit" id="ATTR_PLACEHOLDER_1">
    Submit
</button>
```

Before finalizing the output, Blaze substitutes the original expression back into the HTML.

```blade
<button class="bg-red-500 hover:bg-red-400" type="submit" id="{{ Str::random() }}">
    Submit
</button>
```

This worked because `id` is a **pass-through attribute** — it is outputted without any transformation or without being used in internal component logic.

### Dynamic non-pass-through attributes

When an attribute value is transformed or used in internal component logic, folding can only succeed if that attribute is static. This is because Blaze doesn't have access to dynamic values at compile-time.

>[!IMPORTANT]
> When a component receives a dynamic attribute that is also defined in `@props`, Blaze automatically aborts folding and falls back to function compilation. This is a defensive default which avoids most common errors. Attributes can be marked as `safe` to revert this behavior — we'll explore this concept in [Selective folding](#selective-folding).

To understand why, let's explore an example where folding breaks:

```blade
<x-button :color="$deleting ? 'red' : 'blue'" />
```

Blaze creates the mapping table and pre-renders the component with a placeholder:

| Placeholder | Dynamic value |
|------|--------|
| ATTR_PLACEHOLDER_1 | `$deleting ? 'red' : 'blue'` |

```blade
<x-button color="ATTR_PLACEHOLDER_1">
```

Inside the component, `$color` will now evaluate to the string `"ATTR_PLACEHOLDER_1"`:

```blade
@php
$classes = match("ATTR_PLACEHOLDER_1") {
    'red' => 'bg-red-500 hover:bg-red-400',
    'blue' => 'bg-blue-500 hover:bg-blue-400',
    default => 'bg-gray-500 hover:bg-gray-400',
};
@endphp
```

The match lookup fails and falls back to the default, which results in the button always being gray:

```blade
<button class="bg-gray-500 hover:bg-gray-400 ❌" type="button">
    Submit
</button>
```

### Slots

Slots are handled similarly to attributes — they are replaced with placeholders during pre-rendering and restored afterwards. 

>[!IMPORTANT]
> Unlike attributes, slots are always considered as **pass-through** — they will never abort folding unless marked as `unsafe` (see [Selective folding](#selective-folding)).

```blade
<x-button>{{ $action }}</x-button>
```

During compilation, Blaze analyzes the component and stores slot values:

| Placeholder | Dynamic value |
|------|--------|
| SLOT_PLACEHOLDER_1 | `{{ $action }}` |

Blaze pre-renders the component using the placeholder.

```blade
<x-button>SLOT_PLACEHOLDER_1</x-button>
```

Which results in:

```blade
<button class="bg-red-500 hover:bg-red-400" type="submit">
    SLOT_PLACEHOLDER_1
</button>
```

Before finalizing the output, Blaze substitutes the original expression back into the HTML.


## Selective folding

By default, Blaze errs on the side of caution and aborts folding when dynamic values are detected in attributes defined in `@props`. This prevents many common mistakes, but can be overly restrictive in some scenarios. Similarly, slots are always considered pass-through, which may not be desirable in some cases.

You can fine-tune folding behavior using the `safe` and `unsafe` parameters of the `@blaze` directive.

### Using `safe`

Use `safe` to mark props that are **pass-through** — they are not transformed or used in internal component logic. This allows folding to proceed even when those props are dynamic.

```blade
@blaze(fold: true, safe: ['level'])

@props(['level' => 1])

<h{{ $level }}>{{ $slot }}</h{{ $level }}>
```

```blade
<x-heading :level="$isFeaturedSection ? 1 : 2" />
```

Now the component folds successfully, producing:

```blade
<h{{ $isFeaturedSection ? 1 : 2 }}></h{{ $isFeaturedSection ? 1 : 2 }}>
```

### Using `unsafe` for slots

Use `unsafe` to mark slots that are **not** pass-through — they are transformed or used in internal component logic. This forces folding to abort.

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

Now the component correctly aborts folding whenever it's used with a slot.

> [!IMPORTANT]
> Blaze does not inspect slot content to determine if it is dynamic or static. When a slot is marked as `unsafe` it will always abort folding regardless of its actual content. Self-closing components will still fold successfully.

### Using `unsafe` for attributes

You can also mark attributes that are not defined in `@props` as unsafe.

This avoids folding errors when **non-pass-through** attributes are resolved dynamically via `$attributes`

```blade
@blaze(fold: true, unsafe: ['href'])

@php
$active = $attributes->get('href') === url()->current();
@endphp

<a {{ $attributes->merge(['data-active' => $active]) }}>
    {{ $slot }}
</a>
```

You can also mark the entire attribute bag as unsafe:

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

    {{ $slot }}
</a>
```

Now any dynamic attributes will cause folding to abort.

## Global State

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

## The Unblaze Directive

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

# Reference

### Directive Parameters

```blade
@blaze(fold: true, safe: ['title'], unsafe: ['attributes'])
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `fold` | `bool` | `false` | Enable compile-time folding |
| `memo` | `bool` | `false` | Enable runtime memoization |
| `safe` | `array` | `[]` | Props that fold even when dynamic |
| `unsafe` | `array` | `[]` | Props that abort folding when dynamic |

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
| `compile` | `true` | Enable Blaze. Set `false` to exclude. |
| `fold` | `false` | Enable compile-time folding |
| `memo` | `false` | Enable runtime memoization |

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
