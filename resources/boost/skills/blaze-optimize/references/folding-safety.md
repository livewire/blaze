# Folding Safety Checklist

Compile-time folding pre-renders components during Blade compilation, embedding static HTML directly into the parent template. All internal logic is baked in at compile time. This eliminates runtime overhead entirely but creates subtle bugs if applied incorrectly.

## Pre-folding checklist

Before marking ANY component with `fold: true`, verify ALL of the following:

### 1. No global state access

The component must NOT internally access any of these. Search the component file for these patterns:

| Category | Patterns to search for |
|----------|----------------------|
| Database | `::where(`, `::find(`, `::get()`, `::all()`, `::first()`, `::count()`, `DB::`, `->get()` on query builders |
| Auth | `auth()`, `auth()->`, `@auth`, `@guest`, `Auth::`, `$user` (if from auth) |
| Session | `session(`, `session()->`, `Session::` |
| Request | `request()`, `request()->`, `Request::`, `$request` |
| Validation | `$errors`, `@error` |
| Time | `now()`, `Carbon::`, `today()`, `time()` |
| CSRF | `@csrf`, `csrf_token()`, `csrf_field()` |
| URL state | `url()->current()`, `url()->previous()`, `request()->path()`, `request()->is(` |
| Config/env | `config(`, `env(` (if the value might change between requests) |
| Cache | `Cache::`, `cache(` |
| App state | `app()->`, `resolve(`, `App::` |

**If ANY of these are found inside the component, do NOT fold it.** The compile strategy is sufficient.

Exception: If global state is used ONLY in a small section, use `@unblaze` to exclude that section:

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

Note: Variables from the component scope must be passed explicitly via the `scope` parameter.

### 2. Props analysis

For each prop defined in `@props`:

**Static props** (always passed as literal strings/numbers) — safe to fold:
```blade
<x-button color="red">Submit</x-button>
```

**Dynamic pass-through props** (passed as expressions but output directly without transformation) — safe IF marked `safe`:
```blade
{{-- Caller: --}}
<x-heading :level="$isFeatured ? 1 : 2" />

{{-- Component must mark the prop as safe: --}}
@blaze(fold: true, safe: ['level'])

@props(['level' => 1])
<h{{ $level }}>{{ $slot }}</h{{ $level }}>
```

**Dynamic non-pass-through props** (passed as expressions AND used in internal logic like match/if/switch) — CANNOT fold:
```blade
{{-- This will break with folding: --}}
<x-button :color="$deleting ? 'red' : 'blue'" />

{{-- Because internally, color drives a match statement: --}}
@php
$classes = match($color) { ... };
@endphp
```

Blaze automatically aborts folding when a dynamic attribute matches a `@props` name, but you must still verify this behavior is correct.

### 3. Slot analysis

**Default slot** — Slots are replaced with placeholders during folding and restored afterwards. They are treated as pass-through by default. This is safe for most cases:
```blade
<x-button>{{ $dynamicLabel }}</x-button>
```

**Slots used in internal logic** — If the component inspects slot content (e.g., `$slot->hasActualContent()`, `$slot->isEmpty()`), mark the slot as `unsafe`:
```blade
@blaze(fold: true, unsafe: ['slot'])

@if ($slot->hasActualContent())
    <div class="has-content">{{ $slot }}</div>
@else
    <span>No results</span>
@endif
```

**Named slots used in logic** — Same rule applies:
```blade
@blaze(fold: true, unsafe: ['footer'])

<div>
    @if($footer->hasActualContent())
        <footer>{{ $footer }}</footer>
    @endif
</div>
```

When a slot is marked `unsafe`, folding is ALWAYS aborted for that component instance regardless of actual content. Self-closing components (no slot passed) will still fold.

### 4. Attribute bag analysis

If the component reads attributes not defined in `@props` via `$attributes->get()` or similar and uses the value in logic (not just pass-through), mark `attributes` as unsafe:

```blade
@blaze(fold: true, unsafe: ['attributes'])

@php
$active = $attributes->get('href') === url()->current();
@endphp

<a {{ $attributes->merge(['data-active' => $active]) }}>
    {{ $slot }}
</a>
```

You can also mark specific non-@props attributes as unsafe:
```blade
@blaze(fold: true, unsafe: ['href'])
```

## Safe/unsafe parameter reference

| Value | What it targets |
|-------|----------------|
| `*` | All props, attributes, and slots |
| `slot` | The default slot |
| `[name]` | A specific prop, attribute, or named slot |
| `attributes` | All attributes not defined in `@props` |

**`safe`** — Tells Blaze that these `@props` values are pass-through, allowing folding to proceed even when they receive dynamic values.

**`unsafe`** — Tells Blaze that these values are NOT pass-through, forcing folding to abort when they receive dynamic values.

## Common folding patterns

### Table cell (good candidate)
```blade
@blaze(fold: true, safe: ['align'])

@props(['align' => 'left'])

<td class="text-{{ $align }}">
    {{ $slot }}
</td>
```
`align` is a pass-through prop (output directly in the class), so it's marked `safe`.

### Icon (better as memo, not fold)
```blade
{{-- Use memo instead — icons are slot-free and frequently repeated --}}
@blaze(memo: true)

@props(['name'])

<svg><!-- ... --></svg>
```

### Menu item (good fold candidate in large menus)
```blade
@blaze(fold: true, safe: ['href'])

@props(['href', 'label'])

<a href="{{ $href }}" {{ $attributes }}>
    {{ $label }}
</a>
```
Works because: `href` and `label` are passed through directly.

### Form input (use @unblaze for error display)
```blade
@blaze(fold: true)

@props(['name', 'label', 'type' => 'text'])

<div>
    <label>{{ $label }}</label>
    <input type="{{ $type }}" name="{{ $name }}" {{ $attributes }}>

    @unblaze(scope: ['name' => $name])
        @error($scope['name'])
            <p class="text-red-500">{{ $message }}</p>
        @enderror
    @endunblaze
</div>
```

## When NOT to fold — even if technically possible

- The project doesn't have measured performance issues — premature optimization
- The component is frequently modified during development — folding requires `view:clear` after changes and adds cognitive overhead for developers
- The component has complex internal logic — harder to reason about safety
