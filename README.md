# 🔥 Blaze

Speed up your Laravel app by optimizing Blade component rendering performance.

```
Rendering 25,000 anonymous components:

Without Blaze  ████████████████████████████████████████  500ms
With Blaze     █                                          13ms
```

## Installation

```bash
composer require livewire/blaze
```

> [!TIP]
> If you're using Flux, just install Blaze and you're good to go!

## Introduction

Rendering large numbers of Blade components can be slow due to Laravel's expensive component pipeline. Blaze eliminates this overhead by compiling components into direct PHP function calls or static HTML.

### Limitations

Blaze is designed as a drop-in replacement for anonymous Blade components. It supports all essential features and the HTML output it produces should be identical to Blade. That said, there are some limitations:

- **Class-based components** are not supported
- **The `$component` variable** is not supported
- **`View::share()`** variables will not be injected
- **View composers / creators** will not run
- **Component lifecycle events** will not fire

> [!IMPORTANT]
> When using `@aware`, both the parent and child must use Blaze for the values to propagate correctly. 

### Usage

Enable Blaze in your `AppServiceProvider`.

This will optimize all [anonymous component paths](https://laravel.com/docs/12.x/blade#anonymous-component-paths).

```php
use Livewire\Blaze\Blaze;

/**
 * Bootstrap any application services.
 */
public function boot(): void
{
    Blaze::optimize();
}
```

> [!CAUTION]
> This can break your app if you rely on features not supported by Blaze. Consider only enabling Blaze for certain directories or components.

### Configuration

To only enable Blaze for specific directories or components:

**Define component paths:**

```php
Blaze::optimize()
    ->in(resource_path('views/components/icons'))
    ->in(resource_path('views/components/ui'));
```

**Or use the @blaze directive:**

```blade
@blaze

<button>
    {{ $slot }}
</button>
```

To enable different strategies per directory/component:

```php
Blaze::optimize()
    ->in(resource_path('...'), memo: true)
    ->in(resource_path('...'), fold: true);
```

```blade
@blaze(memo: true)
@blaze(fold: true)
```

## Optimization strategies

Blaze offers three optimization strategies:

| Strategy | Param | When to use |
|----------|----------|-------------|
| **[Compiler](#function-compiler)** | (default) | For most components - reliable optimization with zero concerns about caching or stale data |
| **[Memoization](#runtime-memoization)** | `memo` | For self-closing components like icons or avatars that appear many times on a page with the same props |
| **[Folding](#compile-time-folding)** | `fold` | For maximum performance - when you understand the folding model and your component's data flow |

## Function compiler

The function compiler transforms your Blade components into optimized PHP functions. This skips the entire component rendering pipeline, eliminating 91-97% of the overhead.

### Benchmark results

The benchmarks below represent 25,000 components rendered in a loop:

| Scenario | Blade | Blaze | Reduction |
|----------|----------------|------------|-----------|
| No attributes | 500ms | 13ms | 97.4% |
| Attributes only | 457ms | 26ms | 94.3% |
| Attributes + merge() | 546ms | 44ms | 91.9% |
| Props + attributes | 780ms | 40ms | 94.9% |
| Default slot | 460ms | 22ms | 95.1% |
| Named slots | 696ms | 49ms | 93.0% |
| @aware (nested) | 1,787ms | 129ms | 92.8% |

These numbers reflect the rendering pipeline overhead, not the work your components do internally. If your components execute expensive operations, that work will still affect performance.

### How it works

When you enable Blaze, it compiles your components into direct function calls:

```blade
@blaze

@props(['type' => 'button'])

<button type="{{ $type }}" class="inline-flex">
    {{ $slot }}
</button>
```

```php
<?php
function _c4f8e2a1($__data, $__slots) {
$type = $__data['type'] ?? 'button';
$attributes = new BlazeAttributeBag($__data);
?>
<button type="<?php echo e($type); ?>" class="inline-flex">
    <?php echo e($slots['default']); ?>
</button>
<?php } ?>
```

When you include a component on a page, instead of resolving the it through Blade's rendering pipeline, Blaze calls the function directly:

```blade
<x-button type="submit">Send</x-button>
```

```php
_c4f8e2a1(['type' => 'submit'], ['default' => 'Send']);
```

## Runtime memoization

**This strategy only works for components without slots.**

Runtime memoization is an optimization for specific types of components like icons and avatars, which are often rendered many times on a page with the same values.

```blade
@blaze(memo: true)

@props(['name'])

<x-dynamic-component :component="'icons.' . $name" />
```

```blade
<x-icon :name="$task->status->icon" />
```

If `<x-icon name="check" />` appears 50 times on a page, it only renders once.

## Compile-time folding

Compile-time folding is Blaze's most powerful optimization strategy, pre-rendering components during compilation to remove virtually all overhead. However, it requires a deep understanding of how folding works.

> [!CAUTION]
> Used incorrectly, folding can cause subtle bugs that are difficult to diagnose. Read this section carefully before enabling it.

### How folding works

```blade
@blaze(fold: true)

@props(['type' => 'button'])

<button type="{{ $type }}" class="inline-flex">
    {{ $slot }}
</button>
```

When you include a component on a page, Blaze renders it at compile-time and embeds the HTML directly into your template:

```blade
<x-button type="submit">Submit</x-button>
```

```blade
<button type="submit" class="inline-flex">
    Submit
</button>
```

The component no longer exists at runtime.

---

### Dynamic attributes

This is where things get more complicated. Blaze can handle dynamic attributes, but only when they **pass through unchanged** via `$attributes`.

Consider a component that doesn't define any props - all attributes are just passed through:

```blade
{{-- components/card.blade.php --}}
@blaze(fold: true)

<div {{ $attributes->class(['rounded-lg shadow-md p-6']) }}>
    {{ $slot }}
</div>
```

When called with a dynamic attribute:

```blade
<x-card :class="$featured ? 'bg-yellow-50' : 'bg-white'">
    Featured content
</x-card>
```

Blaze uses **placeholder replacement** to fold this correctly:

| Step | What happens |
|------|--------------|
| **1. Placeholder** | `<x-card class="__BLAZE_PH_1__">` |
| **2. Render** | `<div class="rounded-lg shadow-md p-6 __BLAZE_PH_1__">` |
| **3. Replace** | `<div class="... <?php echo e($featured ? '...' : '...'); ?>">` |

This works because the value passes through `$attributes` unchanged - Blaze doesn't need to know what the value is, just where it ends up.

---

### @props

Problems occur when a dynamic value is captured by `@props` and used in logic or transformations:

```blade
{{-- components/status-badge.blade.php --}}
@blaze(fold: true)

@props(['status'])

@php
    $colors = [
        'active' => 'bg-green-100 text-green-800',
        'pending' => 'bg-yellow-100 text-yellow-800',
        'inactive' => 'bg-gray-100 text-gray-800',
    ];
@endphp

<span class="px-2 py-1 rounded-full text-sm {{ $colors[$status] ?? $colors['inactive'] }}">
    {{ $status }}
</span>
```

When called with a **dynamic** prop:

```blade
<x-status-badge :status="$user->status" />
```

Here's what goes wrong:

| Step | What happens |
|------|--------------|
| **1. Placeholder** | `<x-status-badge status="__BLAZE_PH_1__" />` |
| **2. Render** | `$colors["__BLAZE_PH_1__"]` fails lookup, falls back to `inactive` |
| **3. Result** | `<span class="... bg-gray-100 text-gray-800">__BLAZE_PH_1__</span>` |

The color is permanently `inactive` because the array lookup ran with the placeholder string, not the actual value.

**This is why Blaze aborts folding when a defined prop receives a dynamic value.**

---

### The `safe` parameter

If a prop is only passed through to the output and never used in conditions, transformations (like `Str::title($prop)`), or lookups, you can mark it as `safe`:

```blade
@blaze(fold: true, safe: ['title'])

@props(['title', 'level' => 2])

<h{{ $level }} class="font-bold text-gray-900">
    {{ $title }}
</h{{ $level }}>
```

Now dynamic titles fold successfully:

```blade
<x-heading :title="$post->title" />  {{-- Folds --}}
```

> [!WARNING]
> Only mark props as safe if they truly pass through unchanged. If you use a "safe" prop in a condition like `@if($title)` or transform it like `{{ Str::upper($title) }}`, the condition/transformation will be evaluated at compile-time with a placeholder value, causing bugs.

**Mark all props as safe:**

```blade
@blaze(fold: true, safe: ['*'])
```

---

### The `unsafe` parameter

Sometimes you need to prevent folding when certain dynamic values are present.

**When any dynamic attribute would break the component:**

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

With `unsafe: ['attributes']`, if **any** dynamic attribute is passed, folding aborts and the component falls back to function compilation.

**When a specific attribute breaks the component:**

If only certain attributes are used in logic, you can be more precise:

```blade
@blaze(fold: true, unsafe: ['attributes.variant'])

@php
    $icon = match($attributes->get('variant')) {
        'success' => 'check-circle',
        default => 'info-circle',
    };
@endphp

<div {{ $attributes->except('variant') }}>
    <x-icon :name="$icon" />
    {{ $slot }}
</div>
```

Now `<x-alert :variant="$type">` aborts folding, but `<x-alert :class="$classes">` still folds.

---

### Slots

All slots are considered **safe by default** - their content passes through to the output unchanged.

However, this means slot inspection methods won't work as expected:

```blade
{{-- This won't work correctly when folded --}}
@if($slot->isNotEmpty())
    <div class="content">{{ $slot }}</div>
@endif
```

Because slots are replaced with placeholders during folding, `$slot->isNotEmpty()` will always return `true` (the placeholder is not empty).

If you need to inspect slot content, mark the slot as unsafe:

```blade
@blaze(fold: true, unsafe: ['slot'])

@if($slot->isNotEmpty())
    <div class="content">{{ $slot }}</div>
@endif
```

For named slots:

```blade
@blaze(fold: true, unsafe: ['footer'])

@if($footer->isNotEmpty())
    <footer>{{ $footer }}</footer>
@endif
```

---

### Global state

When components are folded, they are rendered at compile-time. This means any global state is captured at the moment of compilation, not at runtime.

Consider this component:

```blade
@blaze(fold: true)

@if(auth()->check())
    <span>Welcome, {{ auth()->user()->name }}</span>
@else
    <span>Please log in</span>
@endif
```

If a logged-in user triggers the compilation (by being the first to visit the page after a cache clear), the "Welcome" message gets baked into the template. **All subsequent users - including logged-out users - will see the same content.**

> [!WARNING]
> Components that read global state should never be folded. Use `@blaze` (function compilation) instead, or wrap the dynamic section in `@unblaze`.

**Common global state to watch for:**

| Category | Examples |
|----------|----------|
| Authentication | `auth()->check()`, `auth()->user()`, `@auth`, `@guest` |
| Session | `session('key')`, `session()->get()` |
| Request | `request()->path()`, `request()->is()` |
| Validation | `$errors->has()`, `$errors->first()` |
| Time | `now()`, `Carbon::now()` |
| Security | `@csrf` |

---

### The @unblaze directive

When you have a component that's mostly static but needs one dynamic section, use `@unblaze` to exclude that section from folding:

```blade
@blaze(fold: true)

@props(['name', 'label'])

<div class="form-group">
    <label class="block text-sm font-medium text-gray-700">{{ $label }}</label>
    <input type="text" name="{{ $name }}" class="mt-1 block w-full rounded-md border-gray-300">

    @unblaze
        @error($name)
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    @endunblaze
</div>
```

The label and input are folded (static). The error handling remains dynamic and executes at runtime.

**Passing data into @unblaze:**

Variables from the component scope aren't automatically available inside `@unblaze`. Use the `scope` parameter:

```blade
@unblaze(scope: ['name' => $name])
    @error($scope['name'])
        <p class="text-red-600">{{ $message }}</p>
    @enderror
@endunblaze
```

---

### Folding checklist

Before enabling `fold: true` on a component, verify:

- [ ] **No global state** - No `auth()`, `session()`, `request()`, `$errors`, `now()`, `@csrf`, `@auth`/`@guest`
- [ ] **No class-based child components** - All nested `<x-*>` components must be anonymous
- [ ] **Props are pass-through OR marked safe** - If a prop is used in conditions/transformations, don't mark it safe
- [ ] **Attributes used in logic are marked unsafe** - If you call `$attributes->get()` in conditions, mark `attributes` or the specific attribute as unsafe
- [ ] **Slots used in conditions are marked unsafe** - If you check `$slot->isNotEmpty()`, mark that slot as unsafe
- [ ] **No `View::share()` or view composers** - Unless you've enabled them with `share: true` or `composer: true`

**When in doubt, use `@blaze` without folding.** Function compilation provides excellent performance (94-97% overhead reduction) with none of the footguns.

## Reference

### Directive parameters

```blade
@blaze(fold: true, safe: ['title'], unsafe: ['attributes'])
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `fold` | `bool` | `false` | Enable compile-time folding |
| `memo` | `bool` | `false` | Enable runtime memoization |
| `safe` | `array` | `[]` | Props that can be folded even when dynamic |
| `unsafe` | `array` | `[]` | Props/attributes/slots that abort folding when dynamic |

**Special values for `safe` and `unsafe`:**

| Value | Target |
|-------|--------|
| `'*'` | All props |
| `'attributes'` | The entire `$attributes` bag |
| `'attributes.name'` | A specific attribute |
| `'slot'` | The default slot |
| `'slotName'` | A named slot |

### Folder configuration

```php
Blaze::optimize()
    ->in(resource_path('views/components/ui'), fold: true)
    ->in(resource_path('views/components/icons'), memo: true)
    ->in(resource_path('views/components/legacy'), composer: true, share: true)
    ->in(resource_path('views/components/dynamic'), compile: false);
```

| Option | Default | Description |
|--------|---------|-------------|
| `compile` | `true` | Enable Blaze. Set `false` to exclude. |
| `fold` | `false` | Enable compile-time folding |
| `memo` | `false` | Enable runtime memoization |
| `composer` | `false` | Enable view composers |
| `share` | `false` | Enable `View::share()` variables |
| `events` | `false` | Enable component lifecycle events |

> [!TIP]
> Component-level `@blaze` directives override folder-level settings.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
