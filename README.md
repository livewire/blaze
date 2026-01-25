# 🔥 Blaze

Speed up your Laravel app by optimizing Blade component rendering performance.

```
Rendering 25,000 anonymous components:

Without Blaze  ████████████████████████████████████████  500ms
With Blaze     █                                          13ms
```

# Introduction

Out of the box, Blaze is a **drop-in replacement** for Blade components which does not require any changes to your existing component code. 

It works by compiling components into optimized PHP code instead of using the standard rendering pipeline — this eliminates 91-97% of the  overhead while maintaining near full feature parity with Blade.

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

To get started with Blaze, all you need to do is call `Blaze::optimize()` in your `AppServiceProvider`.

This enables Blaze for all components by default. **It is highly recommended** to only enable Blaze for specific folders or components if you're integrating it into an existing application. This allows you to gradually adopt Blaze and verify compatibility - see the [Configuration](#configuration) section for more details.

```php
use Livewire\Blaze\Blaze;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Blaze::optimize();

    // ...
}
```

After enabling Blaze, you should clear your compiled views:

```bash
php artisan view:clear
```

# Limitations

Blaze supports all essential features and produces HTML output that is identical to Blade. The focus is on maximizing performance while maintaining compatibility. That said, there are some limitations to be aware of:

- **Class-based components** are not supported
    
    Class instantiation introduce significant overhead. Blaze only focuses on optimizing anonymous components, which are often used for building design systems and UI libraries. If your component requires complex logic it is likely not a good candidate for Blaze optimization.

    The `$component` variable, which normally points to the component class instance, will also not be available.

- **View composers / creators / lifecycle events** will not fire

    TODO

- **Auto-injecting `View::share()` variables** is not supported

    TODO
    
    Variables shared via `View::share()` will NOT be injected automatically. You have to access them manually:

    ```blade
    {{ $__env->shared('key') }}
    ```

- **Cross boundary `@aware`** between Blade and Blaze components

    While `@aware` is supported, you must use Blaze in both parent and child for values to propagate correctly.

# Configuration

Blaze provides flexible configuration options to let you gradually adopt it into your application or enable different optimizations for different components.

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

# Optimization Strategies

By default Blaze uses **Function Compilation**, which works for virtually all components and provides significant performance improvements — this is sufficient for most use cases.

To go even further, you can consider the other strategies that require additional considerations.

| Strategy | Parameter | Best For | Overhead Reduction |
|----------|-----------|----------|-------------------|
| Function Compilation | (default) | General use | 91-97% |
| Runtime Memoization | `memo` | Repeated identical components | 91-97% + deduplication |
| Compile-Time Folding | `fold` | Maximum performance | 100% |


# Function Compilation

This strategy transforms your components into optimized PHP functions, bypassing the entire component rendering pipeline while maintaining identical behavior to standard Blade.

### Benchmark Results

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

> These numbers reflect rendering pipeline overhead. If your components execute expensive operations internally, that work will still affect performance when using this strategy.

## How It Works

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

# Runtime Memoization

Runtime memoization caches component output during a single request. When a component renders with the same props multiple times, it only executes once. This strategy is ideal for components like icons and avatars that appear many times with identical values:

```blade
@blaze(memo: true)

@props(['name'])

<x-dynamic-component :component="'icons.' . $name" />
```

When you include the component on a page, Blaze caches the output based on the actual prop values:

```blade
<x-icon :name="$task->status->icon">
```

Because there might be multiple tasks with the same status, we don't need to re-render the icon each time. Blaze stores the output in a cache using a hash of the prop values as the key and returns the cached output on subsequent renders.

> [!NOTE]
> Memoization only works for components without slots.

# Compile-Time Folding

Compile-time folding is Blaze's most aggressive optimization. It pre-renders components during compilation, embedding the HTML directly into your template. The component ceases to exist at runtime - there is no function call, no variable resolution, no overhead whatsoever.

## Benchmark Results

Because folded components are rendered at compile-time, the runtime cost is effectively zero. The rendering time remains constant regardless of how many components you use:

| Components | Blade | Blaze (folded) |
|------------|-------|----------------|
| 25,000 | 500ms | 0.68ms |
| 50,000 | 1,000ms | 0.68ms |
| 100,000 | 2,000ms | 0.68ms |

> [!CAUTION]
> **Folding requires careful consideration**. Used incorrectly, it can cause subtle bugs that are difficult to diagnose. Make sure you fully understand the limitations described below before using this strategy.

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

### Dynamic attributes

Because Blaze pre-renderes components during compilation, it doesn't have access to data that will be passed to the components at runtime. It works around this by replacing dynamic values with placeholders during rendering and substituting the original expressions back into the final output.

To illustrate this, let's assign a random id to the button:

```blade
<x-button color="red" :id="Str::random()">Submit</x-button>
```

During compilation, Blaze will analyze the component to identify dynamic values and store their values:

| Property | Dynamic value | Placeholder |
|------|--------|--------|
| `id` | `Str::random()` | ATTR_PLACEHOLDER_1 |

Next, it pre-renders the component using the placeholder:

```blade
<x-button color="red" :id="ATTR_PLACEHOLDER_1">Submit</x-button>
```

Which results in:

```blade
<button class="bg-red-500 hover:bg-red-400" type="submit" id="ATTR_PLACEHOLDER_1">
    Submit
</button>
```

Before finalizing the output, Blaze substitutes the original dynamic expressions back into the HTML using the mapping table:

```blade
<button class="bg-red-500 hover:bg-red-400" type="submit" id="{{ Str::random() }}">
    Submit
</button>
```

### Dynamic props

In the previous example, the dynamic attribute was handled successfully because it was passed directly to the HTML output. However, if a dynamic prop is used in conditions or transformations, folding may fail.

Let's now use a dynamic variable for the `color` prop which is used in a `match` statement:

```blade
<x-button :color="$deleting ? 'red' : 'blue'" />
```

Blaze creates the mapping table and pre-renders the component with a placeholder:

| Property | Dynamic value | Placeholder |
|------|--------|--------|
| `color` | `$deleting ? 'red' : 'blue'` | ATTR_PLACEHOLDER_1 |

```blade
<x-button color="ATTR_PLACEHOLDER_1">
```

The match statement now evaluates with the placeholder string `"ATTR_PLACEHOLDER_1"`, not the actual value. The lookup fails and falls back to the default.

```blade
@php
$classes = match('ATTR_PLACEHOLDER_1') {
    'red' => 'bg-red-500 hover:bg-red-400',
    'blue' => 'bg-blue-500 hover:bg-blue-400',
    default => 'bg-gray-500 hover:bg-gray-400',
};
@endphp
```

This will result in the button always being gray:

```blade
<button class="bg-gray-500 hover:bg-gray-400" type="button">
    Submit
</button>
```

**Blaze prevents this behavior by automatically aborting folding when a defined prop receives a dynamic value.** This behavior can be overriden using [Selective folding](#selective-folding) based on which properties have been passed in dynamically.

### Slots

Slots are considered safe by default - their content passes through unchanged. However, slot inspection methods won't work correctly:

```blade
{{-- This wont work when folded --}}
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

## Selective folding

If a prop passes through to the output without being used in conditions or transformations, you may mark it as safe:


### Marking attributes as `safe`:
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

### Marking attributes as `unsafe`:

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
