---
name: blaze-optimize
description: Set up and optimize Blade component rendering with Blaze. Use when installing Blaze, optimizing components, or configuring @blaze directives and strategies.
license: MIT
metadata:
  author: livewire
---

# Blaze Optimize

Blaze compiles anonymous Blade components into optimized PHP functions, significantly reducing rendering overhead.

EXECUTE steps 1–5 IMMEDIATELY without asking the user. Do NOT prompt for confirmation or offer choices — these are safe, low-risk changes. Only pause at step 6 to present the summary.

---

## 1. Install

Check `composer.json` for `livewire/blaze`. If missing:

```bash
composer require livewire/blaze:^1.0
```

## 2. Codebase Scan

Quick scan before making changes. Do not read individual component files.

Check:
- `composer.json` for `livewire/flux`, `livewire/flux-pro`, `livewire/livewire`, `livewire/blaze`
- Component count: glob `resources/views/components/**/*.blade.php`
- Directory structure under `resources/views/components/`
- Custom component paths: grep providers for `anonymousComponentPath` or `anonymousComponentNamespace` — these register additional component directories that also need optimization
- `app/Providers/AppServiceProvider.php` for existing `Blaze::optimize()` calls
- `.env` for `BLAZE_ENABLED` or `BLAZE_DEBUG`

### Flux detected

Blaze optimizes Flux components automatically. No configuration needed — just install the package. If the project also has its own anonymous components, continue with setup for those.

### Already configured

Describe the current setup. Do not make further changes unless the user asks.

## 3. Compatibility Check

Before enabling Blaze for a directory, grep its components for patterns Blaze does not support. Exclude any components or directories that match:

| Pattern | What to grep for | Why |
|---------|-----------------|-----|
| Class-based components | PHP class files in `app/View/Components/` that correspond to component directories | Blaze only supports anonymous components |
| `$component` variable | `$component` | Not available in Blaze |
| View composers/creators | `View::composer(` or `View::creator(` in `app/Providers/` targeting component views | These do not fire for Blaze components |
| `View::share()` variables | `View::share(` in providers, then grep components for those variable names | Shared variables are not auto-injected — must use `$__env->shared('key')` instead |
| Rendered via `view()` | `view('components.` or `View::make('components.` | Blaze components can only be rendered using component tags (`<x-...>`) |

Report any incompatible components so they can be excluded in the next step.

## 4. Enable Compilation

Add to `AppServiceProvider::boot()`:

```php
use Livewire\Blaze\Blaze;

public function boot(): void
{
    Blaze::optimize()->in(resource_path('views/components'));
}
```

Include any custom component paths found in step 2:

```php
Blaze::optimize()
    ->in(resource_path('views/components'))
    ->in(resource_path('views/other-components'));
```

Exclude any directories or files flagged in the compatibility check:

```php
Blaze::optimize()
    ->in(resource_path('views/components'))
    ->in(resource_path('views/components/legacy'), compile: false)                    // directory
    ->in(resource_path('views/components/complex-widget.blade.php'), compile: false); // single file
```

## 5. Enable Debug + Clear Views

Set `BLAZE_DEBUG=true` in `.env` so trace data is collected when the user browses pages. Then clear cached views:

```bash
php artisan view:clear
```

## 6. Summary Report

Keep the summary SHORT and concise. A few bullet points, no tables, no per-directory breakdowns. Only mention what changed and what was excluded. Example:

- Compilation enabled for `resources/views/components` (72 components)
- No incompatible components found
- `BLAZE_DEBUG=true` set in `.env`

If components were excluded, list them briefly. If there are refactors to suggest, add:

- **Suggested refactors:**
  - **Class-based components** — convert to anonymous so Blaze can compile them
  - **View::share / composer / creator reliance** — pass data via props instead, or use `$__env->shared('key')`

Then present the advanced optimization options. Use the SAME formatting as written below — numbered options with a clear ask for the user to pick one. Only include Option 3 if the compatibility check found refactors to suggest. If there are no refactors, omit Option 3 entirely. Mark Option 3 as RECOMMENDED when present, otherwise mark Option 1 as RECOMMENDED.

**When there are refactors to suggest:**

> **Want to go further?**
>
> **Option 1 — Trace-based optimization**
> Browse your app with debug mode enabled (already active), make sure to visit the **slow or component-heavy pages**, then tell me you're done. I'll analyze the trace data and apply targeted memo/fold optimizations to the slowest components.
>
> **Option 2 — Code analysis**
> Full codebase audit without trace data. **WARNING:** significantly more **token-intensive**, results are **speculative**, and incorrect optimizations can break components. Requires thorough testing.
>
> **Option 3 — Apply refactors first (RECOMMENDED)**
> Apply the suggested refactors to bring excluded components into Blaze compilation. I'll come back to these options when done.
>
> Reply **1**, **2**, or **3** when ready (or skip if you don't need advanced optimization).

**When there are NO refactors:**

> **Want to go further?**
>
> **Option 1 — Trace-based optimization (RECOMMENDED)**
> Browse your app with debug mode enabled (already active), make sure to visit the **slow or component-heavy pages**, then tell me you're done. I'll analyze the trace data and apply targeted memo/fold optimizations to the slowest components.
>
> **Option 2 — Code analysis**
> Full codebase audit without trace data. **WARNING:** significantly more **token-intensive**, results are **speculative**, and incorrect optimizations can break components. Requires thorough testing.
>
> Reply **1** or **2** when ready (or skip if you don't need advanced optimization).

## STOP

You are done. Do NOT proceed with advanced optimization (memoization, folding) unless the user explicitly asks for it. When they do, read [references/advanced-optimization.md](references/advanced-optimization.md) for instructions.
