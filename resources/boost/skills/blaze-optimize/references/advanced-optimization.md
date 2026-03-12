# Advanced Optimization

These optimizations (memoization and folding) are more powerful but carry risk — they can affect component behavior if applied incorrectly and should be thoroughly tested.

## Option A: Trace-Based (preferred)

### 1. Collect trace data

Ask the user to visit pages in their browser, making sure to include the slow pages they want to optimize. `BLAZE_DEBUG=true` is already enabled from Phase 1, so trace data will be collected automatically.

Wait for the user to confirm they are done browsing before proceeding.

### 2. Analyze traces

Run:

```bash
php artisan blaze:trace:list
```

This shows pages the user visited with their load times. Identify the slowest pages, then drill into them:

```bash
php artisan blaze:trace:show [id]
```

This shows the slowest components for that page and how many were rendered.

### 3. Optimize slow components

Be THOROUGH and PROACTIVE. Analyze EVERY high-volume component from the trace data — do NOT dismiss components without reading them first. If a component is slow and has high render count, read it and determine what optimization applies. Apply optimizations immediately — do not ask the user for permission on each one.

For each slow component identified in the trace data:

1. Read the component file
2. Evaluate it against BOTH memoization and folding — they are not mutually exclusive buckets. A component may fail one but qualify for the other.
3. When applying folding, ALWAYS evaluate every prop, slot, and attribute for safe/unsafe. Never apply bare `@blaze(fold: true)` without checking.

**Memoization** — the MOST effective optimization for high-volume components. Use when:
- Has no slots
- Has no global state access
- Is rendered many times with the same props (icons, avatars, badges)

IMPORTANT: If a high-volume component has a simple slot that only outputs text (e.g. `{{ $slot }}`), it is almost certainly better as a prop. Flag it for refactoring — converting the slot to a prop (like `label` or `text`) makes it memoizable, which is far more effective than folding at high render counts. See the "Suggest Refactors" section below.

Apply via service provider (preferred for entire directories like `icons/`):

```php
Blaze::optimize()
    ->in(resource_path('views/components'))
    ->in(resource_path('views/components/icons'), memo: true);
```

Or via directive (individual components):

```blade
@blaze(memo: true)
```

**Folding** — use when the component:
- Has no global state access (or state is isolated with `@unblaze`)
- Is rendered frequently, especially in loops

Components with slots CAN be folded — slots are pass-through by default. Only mark a slot `unsafe` if the component inspects it.

For each folding candidate:

1. Check for global state patterns — if ANY are found inside the component, do NOT fold. See [folding-safety.md](folding-safety.md) for the complete list.
2. Classify EVERY `@props` value individually:
   - Pass-through (output directly in HTML, class strings, attributes) → mark `safe`
   - Used in logic (match, if/else, switch, ternary with multiple branches) → leave as default (auto-abort when dynamic)
3. Check if slots are inspected (`$slot->hasActualContent()`, `$slot->isEmpty()`, `$slot->isNotEmpty()`) → mark as `unsafe`. If slots are just output (`{{ $slot }}`), they are pass-through and need no annotation.
4. Check if `$attributes->get()` is used in logic → mark `attributes` as `unsafe`
5. Add the directive with the CORRECT safe/unsafe parameters

```blade
@blaze(fold: true)
@blaze(fold: true, safe: ['level'])
@blaze(fold: true, unsafe: ['slot'])
@blaze(fold: true, unsafe: ['attributes'])
```

For components that are mostly foldable but have a small section using global state, use `@unblaze`:

```blade
@unblaze(scope: ['name' => $name])
    @error($scope['name'])
        <p class="text-red-500">{{ $message }}</p>
    @enderror
@endunblaze
```

Note: Variables from the component scope must be passed explicitly via the `scope` parameter.

## Option B: Code Analysis (fallback)

Warn the user that this approach is token-intensive and the optimizations are speculative compared to trace-based analysis.

Use a subagent to perform a full component audit. The subagent should use Glob and Grep extensively — do not read files line by line. For projects with 200+ components, use grep-based sampling.

The subagent should return a structured report covering:

1. **Component inventory** — For each component under `resources/views/components/`, grep for: `@props`, `{{ $slot }}` / named slots, slot inspection patterns (`$slot->hasActualContent()`, `$slot->isEmpty()`, `$slot->isNotEmpty()`), global state patterns (`auth()`, `@auth`, `@guest`, `Auth::`, `session(`, `Session::`, `request()`, `Request::`, `$errors`, `@error`, `now()`, `Carbon::`, `@csrf`, `csrf_`, `url()`, `route(`, `DB::`, `::where(`, `::find(`, `::first(`, `::get()`, `::all()`, `::count(`, `config(`, `cache(`)

2. **Usage analysis** — Grep all views for `<x-` tags. Count per component. List top 20 most-used.

3. **Loop detection** — Find `<x-` tags inside `@foreach`, `@for`, `@forelse`, `@while` blocks.

4. **Nesting analysis** — Which components use other `<x-` components internally.

Report format:

```
COMPONENT AUDIT
===============
Total components: [N]

ALL COMPONENTS:
- [path] — uses: [N], slots: [y/n], inspects-slots: [y/n], global-state: [y/n], loop: [y/n], props: [list]

MEMOIZATION CANDIDATES:
- [name] — [count] uses, no slots, no global state

FOLDING CANDIDATES:
- [name] — [count] uses, in [N] loops, slots: [y/n], inspects-slots: [y/n], props: [list], global-state: none

GLOBAL STATE (cannot fold):
- [name] — uses: [patterns found]

NESTING CHAINS:
- [parent] -> [child] -> [grandchild]
```

Apply memoization and folding using the same rules as Option A.

## Suggest Refactors

After applying optimizations, identify components that are close to qualifying for a higher optimization level but are blocked by a small issue. Present these refactors to the user and APPLY them unless the user declines. Do not just list them and wait — tell the user what you're going to do and do it.

Only suggest refactors that are straightforward and have a clear payoff. Do not refactor components that are rarely used or where compile is already sufficient.

**Slots blocking memoization** — High-volume components rendered many times with the same props but using a simple slot that could be a prop instead. Refactor the component to accept a `label`/`text` prop, update ALL callers, then switch from fold/compile to memo:

```blade
{{-- Before: has slot, cannot memoize --}}
<x-badge color="green">Active</x-badge>

{{-- After: slot replaced with prop, can memoize --}}
<x-badge color="green" label="Active" />
```

When applying this refactor: grep for ALL callers, update every one, update the component, and change the strategy to `memo: true`.

**Global state blocking folding** — Components used in loops that would be good folding candidates, but contain a section that accesses global state. Extract the stateful part with `@unblaze`:

```blade
{{-- Before: $errors blocks folding --}}
<div>
    <label>{{ $label }}</label>
    <input name="{{ $name }}">
    @error($name) <p>{{ $message }}</p> @enderror
</div>

{{-- After: stateful section extracted with @unblaze --}}
@blaze(fold: true)
<div>
    <label>{{ $label }}</label>
    <input name="{{ $name }}">
    @unblaze(scope: ['name' => $name])
        @error($scope['name']) <p>{{ $message }}</p> @enderror
    @endunblaze
</div>
```

## Verification

After applying any advanced optimizations:

1. Run `php artisan view:clear`
2. Test the application — verify optimized components render correctly
3. If using trace-based optimization, ask the user to revisit the same pages and re-run `php artisan blaze:trace:list` to compare render times
4. If a component breaks, remove its `@blaze` directive or add `compile: false`

## Common Pitfalls

- Memoizing components that have slots — memoization requires slot-free components
- Folding components that access global state (auth, session, request, errors, time, CSRF) — produces stale HTML
- Folding components where props drive internal logic without marking them `safe`
- Forgetting to run `php artisan view:clear` after changes
