# Blaze

Blaze is a pre-compiler that massively improves the rendering performance of your app's Blade components.

It uses a tokenized parser to identify static portions of your templates and uses code-folding to remove much of Blade's runtime overhead.

## High-level overview

Blaze hooks into Blade compilation using affordances like `Blade::precompiler(...)` and pre-renders components at compile-time so that they don't even run at runtime.

Here's the general flow:

1. Hook into precompilation
2. Parse template into tokens
3. Assemble tokens into AST (abstract syntax tree)
4. Walk AST and pre-render eligible blade components
5. Assemble AST back into Blade source

Let's dig deeper into each of the above steps to understand how they work together to achieve the code-folding optimization.

## Step 1) Hook into precompilation

Because Blaze optimizes Blade source, it needs to run before Blade actually compiles a file's source.

Fortunately, there is a convenient hook provided by Laravel:

```php
Blade::precompiler(function (string $input) {
    $output = Blaze::compile($input);

    return $output;
});
```

Now, Blaze sits right in front of Blade compilation and can make any optimizations it needs _just in time_.

> **Note**: While this approach seems straightforward, there are actually several technical hurdles that make this more complex in practice. We'll cover the simplified version here for clarity, but see the "[Precompilation hook timing](#precompilation-hook-timing)" section later for the full implementation details.

## Step 2) Parse template into tokens

Because we are only concerned with Blade tag components, our tokens will solely focus on classifying parts of the template strictly related to those, not other parts of Blade. This makes our implementation simpler and more focused.

Consider the following Blade template:

```php
Hello {{ $name }}

<x-button size="lg">Log out</x-button>
```

We can break up the above template into the following flat array of tokens:

```php
array:4 [
  0 => array:2 [
    "type" => "text"
    "content" => """
      Hello {{ $world }}\n
      \n
      """
  ]
  1 => array:5 [
    "type" => "tag_open"
    "prefix" => "x-"
    "namespace" => ""
    "name" => "button"
    "attributes" => "size="lg""
  ]
  2 => array:2 [
    "type" => "text"
    "content" => "Log out"
  ]
  3 => array:4 [
    "type" => "tag_close"
    "prefix" => "x-"
    "namespace" => ""
    "name" => "button"
  ]
]
```

Here is a list of all potential tokens:

```php
'text' // For regular text content...
'tag_open' // For opening component tags (like <x-button>)...
'tag_close' // For closing component tags (like </x-button>)...
'tag_self_close' // For self-closing component tags (like <x-icon />)...
'slot_open' // For opening slot tags (like <x-slot name="header"> or <x-slot:header>)...
'slot_close' // For closing slot tags (like </x-slot> or </x-slot:header>)...
```

Internally those tokens are constructed using the following parser states as the parser walks character by character:

```php
'STATE_TEXT' // Parsing regular text content...
'STATE_TAG_OPEN' // Parsing opening component tags...
'STATE_TAG_NAME' // Parsing tag names...
'STATE_ATTRIBUTE_NAME' // Parsing attribute names and values...
'STATE_ATTRIBUTE_VALUE' // Parsing attribute values...
'STATE_SLOT' // Parsing slot tags...
'STATE_SLOT_NAME' // Parsing slot names...
'STATE_TAG_CLOSE' // Parsing closing component tags...
'STATE_SLOT_CLOSE' // Parsing closing slot tags...
'STATE_SHORT_SLOT' // Parsing short slot syntax (like <x-slot:name>)...
```

## Step 3) Assemble tokens into AST (abstract syntax tree)

After the tokens are collected, they can be assembled into a more structured AST.

To demonstrate the advantages of the AST representation, let's use an example with a nested component inside a slot:

```php
Hello {{ $world }}

<x-button size="lg">
    <x-heading>Log out</x-heading>
</x-button>
```

```php
array:2 [
  0 => array:2 [
    "type" => "text"
    "content" => """
      Hello {{ $world }}\n
      \n
      """
  ]
  1 => array:5 [
    "type" => "tag"
    "name" => "button"
    "prefix" => "x-"
    "attributes" => "size="lg""
    "children" => array:1 [
      0 => array:5 [
        "type" => "tag"
        "name" => "heading"
        "prefix" => "x-"
        "attributes" => ""
        "children" => array:1 [
          0 => array:2 [
            "type" => "text"
            "content" => """
              \n
                  Log out
              """
          ]
        ]
      ]
    ]
  ]
]
```

Now that we have an AST that tells us more about the structure of the template, we can analyze the template more intelligently to identify static portions.

## Step 4) Walk AST and pre-render (fold) eligible blade components

This is the most complex step in the process, as it involves the core optimization logic. We need to intelligently determine which components can be safely folded and then perform the actual folding operation.

To intelligently fold eligible nodes, we need to walk the AST in two passes. The first is a pre-order traversal (the tree is walked left-to-right meaning parent nodes are encountered before their children). The second is post-order (right-to-left meaning children are encountered before their parents).

The first, pre-order, walk is used to collect information about each component and which attributes are being passed into them. The second, post-order, walk is used to actually perform the folding.

Here is a high-level summary of the process:

A) Walk tree left to right (parents first, pre-order)
    * Examine source and construct useful metadata about each component
    * Identify deal-breakers in source and metadata that disqualify a component as being unfoldable
B) Walk tree right to left (children first, post-order)
    * Skip unfoldable nodes
    * Fold eligible nodes

Let's first look at the pre-order traversal phase:

### Pre-order traversal

The primary goal during this phase is to collect information about the component and attributes being passed into it.

Here's a list of what we need to learn about a given component:
* A list of attributes being passed in
* Information about the source: filename, filemtime, etc...
* Whether or not the component contains any forbidden, unfoldable, strings (like using `$errors` or `@aware`)

> **Note**: The `@aware` directive allows components to inherit prop values from distant parent components. This creates runtime dependencies that make compile-time folding impossible. See the "[Supporting the @aware directive](#supporting-the-aware-directive)" section for a detailed explanation.

The secondary goal during this phase is constructing an attribute tree that we can use to maintain support for `@aware` remaining compatible with folded components (more on this later).

### Post-order traversal

The goal of the post-order traversal phase is to do the actual code-folding.

Let's first see the granular process of folding a single component.

Consider the following Blade component with dynamic attributes and slots:

```php
<x-button>{{ $cta }}</x-button>
```

We will identify those dynamic portions and replace them with static placeholders:

```php
<x-button>__PLACEHOLDER__</x-button>
```

Then we will render the Blade with the placeholders:

```php
<button type="button" class="btn">__PLACEHOLDER__</button>
```

Then, we ensure that the placeholders exist and are untampered with in the rendered output, and replace them back with the original dynamic expression:

```php
<button type="button" class="btn">{{ $cta }}</button>
```

THAT is code-folding.

Here's a summary of the steps involved in the post-order, code-folding, traversal phase:

* Walk AST post-order
* Replace dynamic portions of attributes and slots inside node with dummy placeholders
* Compile AST node to Blade source
* Render Blade source
* Ensure placeholders are present and untampered with
* If so, replace placeholders back with dynamic portions and return a text node
* Otherwise, return the original node and continue on with the AST walking

Here is sample source code to fully wrap your head around the exact process.

```php
// Walk the abstract syntax tree post-order (depth-first)...
$ast = $this->walk($ast, function ($node) {
    // We only care about component nodes...
    if ($this->nodeIsNotABladeComponent($node)) return;

    // We will skip nodes that fail preconditions from the pre-order traversal...
    if ($this->nodeFailsPrecondition($node)) return;

    // Identify dynamic portions like :foo="$bar" and replace them with placeholders like :foo="'__PLACEHOLDER__'"...
    [$nodeWithPlaceholders, $placeholderReplacementMap] = $this->replaceDynamicPortionsWithPlaceholders($node);

    try {
        // Compile the AST node back to Blade source WITH the placeholders...
        $bladeWithPlaceholders = $this->compileAstNodeBackToBlade($nodeWithPlaceholders);

        // Render the Blade source completely...
        $rendered = Blade::render($bladeWithPlaceholders);

        // Ensure that all placeholders are present and not tampered with...
        if ($this->placeholdersAreWellPreserved($rendered, $placeholderReplacementMap)) {
            // Replace placeholders with original dynamic portions of the template...
            $rendered = $this->putPlaceholdersBack($rendered, $placeholderReplacementMap);

            // Return this entire component now as a rendered text node...
            return $this->newTextNode($rendered);
        }
    } finally {
        // If the node was deemed uneligible or there was any kind of error, return the original node...
        return $node;
    }
});
```

This process is simple enough and works quite well.

## Step 5) Assemble AST back into Blade source

Now that the AST has been transformed, we can re-assemble it back into Blade source.

This step reverses the parsing process, walking the modified AST and generating the optimized Blade template. Here's a simplified version of this system's `compile` method to show how all the steps work together:

```php
public function compile(string $content): string
{
    $tokens = $this->tokenizer->tokenize($content);

    $ast = $this->tokenizer->parse($tokens);

    [$ast, $metadata] = $this->walkTreePreOrderAndConstructMetadata($ast);

    $ast = $this->walkTreePostOrderAndFoldEligibleComponents($ast, $metadata);

    return $this->tokenizer->render($ast);
}
```

## Finer points

Now that you have a broad overview of how this system works, let's explore the practical implementation challenges. While the algorithm above describes the core logic, there are several real-world complications that need to be addressed for a production system.

### Precompilation hook timing

In a perfect world Blaze could use the most standard hook for precompilation (as outlined at the beginning of this writeup):

```php
Blade::precompiler(function (string $input) {
    $output = Blaze::compile($input);

    return $output;
});
```

In theory, this would work perfectly for a tool like Blaze, however there are three hurdles:

**Hurdle #1: Hook order**

When Blade compiles a file's source, it uses the following ordered steps:

1) Run `$this->prepareStringsForCompilationUsing` hooks (identical to precompiler hooks except they run earlier)
2) Store raw blocks (like `@verbatim` and `@php`)
3) Strip Blade comments out (like `{{-- ... --}}`)
4) Compile Blade component tags (turn `<x-button />` into `@component('button')...`)
5) Run `$this->precompilers` hooks
6) Parse tokens
7) Restore raw blocks

Notice that the precompiler hooks run after Laravel has already compiled the Blade component tags.

This is a problem because we are specifically trying to parse and optimize Blade tag components.

Therefore, we need to use the lesser-known `$this->prepareStringsForCompilationUsing` hooks that run at the very beginning of the process.

**Hurdle #2: Volt uses the same hook**

Laravel Volt uses `$this->prepareStringsForCompilationUsing` hooks to parse its single-file components.

This would normally not be a problem, however, its compiler is greedy and strips away things like raw php `<?php ... ?>` blocks, causing all sorts of issues for Blaze.

To overcome this hurdle, we need to mutate the array of hooks to ensure Blaze's hooks are the very first hook to be run, even within the `$this->prepareStringsForCompilationUsing` hooks array.

**Hurdle #3: Nested compilation problems**

Consider the following massively-paired-down code snippet:

```php
Blade::prepareStringsForCompilationUsing(function ($input) {
    return $this->foldEligibleComponents($input, function ($bladeSourceOfSingleComponent) {
        return Blade::render($bladeSourceOfSingleComponent);
    })
})
```

Because Blaze uses code-folding, it must identify Blade components that can be pre-rendered into the compiled output.

If we render those static components directly inside the compile hook, we end up creating a situation that Blade is not used to: compiling within an existing compilation.

The problems occur because Blade uses temporary compiler properties/variables to track things like extracted raw blocks, the current path of the compiling view, etc...

Therefore, we end up with lots of bad results when compiling a string within a compilation hook.

There are two strategies around this:

**Strategy A: Deferred compilation**
Replace parts of the templates with markers, let Blade finish compiling, then go back and replace the markers. This follows a similar pattern to how Blade handles "raw blocks".

**Strategy B: Sandboxed compilation**
Store the current value of the global compiler properties, wipe them clean, perform the nested compilation, then restore all the properties back to their original values.

#### Chosen approach: Strategy B (Sandboxed compilation)

Blaze uses Strategy B for the following reasons:

1. **Simpler AST manipulation** - Strategy A would require tracking markers throughout the AST transformation process, adding significant complexity
2. **Cleaner separation** - Each compilation happens in its own clean environment without leaving artifacts
3. **More predictable** - While we need to track compiler properties, this is more maintainable than ensuring markers are always properly cleaned up

The implementation looks like this:

```php
// Store current compiler state
$savedProperties = [
    'rawBlocks' => $compiler->getRawBlocks(),
    'currentPath' => $compiler->getCurrentPath(),
    // ... other properties
];

// Clear compiler state for nested compilation
$compiler->clearState();

// Perform the nested compilation
$rendered = Blade::render($bladeSourceOfSingleComponent);

// Restore original compiler state
$compiler->restoreState($savedProperties);
```

**Trade-offs acknowledged:**
- We must maintain a list of compiler properties to save/restore
- New Laravel versions might add properties we haven't accounted for
- However, these can be detected through testing and the failure mode is clear (missing property errors) rather than subtle marker cleanup issues

### Cache-busting folded components

Consider a template like this:

```php
<!-- File: dashboard.blade.php -->

<h1>Dashboard</h1>

<x-button>{{ $cta }}</x-button>
```

Normally, when this view is rendered Laravel would compile and cache two files. One for the dashboard view and another for the button component view.

Each of these files filemtimes (file modified times) would be compared with their source file filemtimes to determine if either of them needs to be re-compiled.

However, if the `x-button` component was code-folded away and rendered directly into the compiled output, the cached dashboard file would look this:

```php
<!-- File: dashboard.blade.php -->

<h1>Dashboard</h1>

<button type="button" class="btn">{{ $cta }}</button>
```

There would only be a single compiled file and Laravel would have no idea if the original source code for the button component was "stale" or invalid.

To solve this problem, we need to track all code-folded components and their source filemtimes so that they can be invalidated at runtime.

The solution to this problem requires two additions:
A) A compile-time system to embed folded filemtimes in the compiled view
B) A runtime system to parse and interpret these filemtimes for cache-invalidation

Let's cover each of these systems briefly:

**System A:**

Every time we code-fold a component, we will need to add its source metadata to a global list. Then when finished transforming the AST and compiling it back into Blade source we will need to embed that metadata into the header of the compiled output like so:

```php
<!-- [Folded]:button:12345674 -->

<h1>Dashboard</h1>

<button type="button" class="btn">{{ $cta }}</button>
```

**System B:**

Unfortunately there is no hook into Blade's compiler at the time of source-file cache invalidation so we need to implement our own using the nearest Blade event:

```php
Event::listen('composing:*', function ($event, $params) use ($invalidator) {
    $view = $params[0];

    if (! $view instanceof \Illuminate\View\View) {
        return;
    }

    // Examine header for folded dependencies and their filemtimes...
    if ($this->hasExpiredFoldedDependency($view)) {
        // Trigger a re-compile of this file if any of its children are stale...
        $view->getEngine()->getCompiler()->compile($view->getPath());
    }
});
```

### Supporting the @aware directive

The `@aware` directive presents unique challenges for code-folding because it creates runtime dependencies between parent and child components that can't be resolved at compile-time.

#### Quick @aware recap

Here's a simple example of the problem `@aware` solves:

```php
<x-button.group variant="primary">
    <x-button />
</x-button.group>
```

The `button.group` component receives the `variant` prop, but the nested `button` component doesn't. Rather than manually passing `variant` down to every child, `@aware` allows the child component to automatically inherit it:

```php
{{-- button component --}}
@aware(['variant'])

@props(['variant' => 'outline'])

<button class="btn btn-{{ $variant }}">{{ $slot }}</button>
```

#### The code-folding challenge

This creates two problems for Blaze:

1. **Children with `@aware` can't be folded** - We can't know at compile-time what parent attributes will be available at runtime
2. **Folded parents break `@aware` chains** - When a parent component is folded away, it no longer pushes its attributes onto Laravel's component stack for children to consume

#### The solution

For problem #1, the solution is simple: **don't fold components that use `@aware`**. This is a reasonable trade-off since these components have runtime dependencies by design.

For problem #2, we need to ensure folded components still contribute to the `@aware` chain. When folding a component, we wrap the folded output with PHP that maintains the component stack:

```php
<?php $__env->pushConsumableComponentData(['variant' => 'primary']); ?>
<!-- Folded component output here -->
<div class="btn-group btn-group-primary">...</div>
<?php $__env->popConsumableComponentData(); ?>
```

#### Implementation details

Since Laravel doesn't provide `pushConsumableComponentData` and `popConsumableComponentData` methods, we add them via macros:

```php
$this->app->make('view')->macro('pushConsumableComponentData', function ($data) {
    $this->componentStack[] = new \Illuminate\Support\HtmlString('');
    $this->componentData[$this->currentComponent()] = $data;
});

$this->app->make('view')->macro('popConsumableComponentData', function () {
    array_pop($this->componentStack);
});
```

This approach "fakes" a component on the stack so that deeply nested children can still consume parent attributes via `@aware`, even when the parent has been folded away.

The performance overhead is minimal since we're only manipulating arrays, and this only affects components that have children using `@aware`.

### Folding deal-breakers

Not all components are safe to fold. Consider a link component that seems simple on the surface:

```php
<x-link href="/">Home</x-link>
```

Here's what the component source might look like:

```php
@props(['href'])

<a href="{{ $href }}" class="link {{ request()->is($href) ? 'link-current' : '' }}">
    {{ $slot }}
</a>
```

This appears to be a straightforward component that could easily be code-folded. However, if this component were folded, it would evaluate `request()->is(...)` at compile-time rather than runtime.

The result would be a link that never updates to reflect the current page state—the "active" styling would be frozen based on whatever URL was active during compilation.

#### Detection challenges

We could attempt to intelligently detect whether a component is safe to fold by scanning for common runtime dependencies:

- `request()`
- `$errors`
- `@aware`
- `now()`
- Session helpers
- Authentication checks

However, this approach has fundamental problems:

1. **Incomplete detection** - Any scanning logic will miss edge cases and alternative syntax patterns
2. **Silent failures** - When detection fails, components will exhibit mysterious runtime behavior that's extremely difficult to debug

A developer would see their link component simply never change state, with no indication that code-folding was the culprit.

#### The opt-in approach

There are two viable strategies:

**A) Intelligent detection** - Attempt to automatically identify foldable components
**B) Explicit opt-in** - Require developers to mark components as foldable

Option A is problematic because failed detection leads to silent, hard-to-debug issues. When a component stops working due to undetected runtime dependencies, developers will have no clear indication of the cause.

Option B provides clear developer intent and prevents mysterious failures. Developers must consciously opt-in to folding, making them aware of the constraints.

#### Proposed syntax

The most straightforward approach is a `@blaze` directive placed at the top of component files:

```php
@blaze

@props(['title'])

<h1 class="text-2xl font-bold">{{ $title }}</h1>
```

This clearly signals that the component has no runtime dependencies and is safe to fold.

**Alternative considerations:**
- A `@blaze` directive would tie more directly to this project
- Global configuration could enable automatic detection for those who prefer it
- Directory-based opt-in could reduce per-component boilerplate

However, `@blaze` is the clearest option—it's self-documenting and doesn't create dependencies on specific tooling names. Since Blade may eventually become a deeper dependency of Laravel itself, tool-agnostic naming is preferable.

### Error handling and debugging

Blaze uses a fail-safe approach: when folding encounters any issues, it falls back to normal Blade compilation, ensuring the application never breaks due to optimization attempts.

#### The folding validation process

When Blaze identifies a `@blaze` component as foldable, it follows this validation process:

1. **Replace dynamic content with placeholders** - Dynamic attributes and slots are temporarily replaced with unique placeholder strings
2. **Attempt rendering** - The component is rendered with placeholders in place
3. **Validate placeholder integrity** - All placeholders must be present and unmodified in the output
4. **Restore or abort** - If validation passes, placeholders are replaced with original dynamic content; otherwise, folding is skipped

#### Example: Successful folding

Given this component usage:

```php
<x-button :suffix="$suffix">{{ $name }}</x-button>
```

Blaze generates placeholders:

```php
<x-button suffix="__PLACEHOLDER1__">__PLACEHOLDER2__</x-button>
```

If the rendered output preserves both placeholders:

```php
<button type="button" class="btn">__PLACEHOLDER2__ __PLACEHOLDER1__</button>
```

Blaze successfully replaces them with the original expressions:

```php
<button type="button" class="btn">{{ $name }} {{ $suffix }}</button>
```

#### Example: Failed folding

However, if the component modifies placeholder content (e.g., forcing lowercase):

```php
<button type="button" class="btn">__PLACEHOLDER2__ __placeholder1__</button>
```

Blaze detects the corrupted placeholder (`__placeholder1__` vs `__PLACEHOLDER1__`) and abandons folding for this component.

#### Common failure scenarios

Folding will be skipped when:

- **Rendering exceptions** - Any error during component rendering
- **Placeholder corruption** - Component logic modifies placeholder strings
- **Missing placeholders** - Placeholders are stripped or filtered out
- **Context-dependent rendering** - Component behavior changes based on runtime state

#### Disallowed folding

Even when a component is marked with `@blaze`, Blaze should perform static analysis to detect runtime dependencies that would make folding unsafe. When such patterns are found, Blaze should throw clear compilation errors rather than silently producing broken behavior.

#### Detected unsafe patterns

During the pre-order traversal phase, Blaze scans component source code for these problematic patterns:

**CSRF tokens:**
```php
@blaze  <!-- ❌ Error: CSRF tokens require runtime generation -->

<form method="POST">
    @csrf
    <button type="submit">Submit</button>
</form>
```

**Session access:**
```php
@blaze  <!-- ❌ Error: Session data is runtime-dependent -->

<div>Welcome back, {{ session('username') }}</div>
```

**Authentication checks:**
```php
@blaze  <!-- ❌ Error: Authentication state changes at runtime -->

@auth
    <p>You are logged in</p>
@endauth
```

**Error bag access:**
```php
@blaze  <!-- ❌ Error: Validation errors are request-specific -->

@if($errors->has('email'))
    <span class="error">{{ $errors->first('email') }}</span>
@endif
```

**Request data:**
```php
@blaze  <!-- ❌ Error: Request data varies per request -->

<input type="hidden" value="{{ request()->ip() }}">
```

#### Error messages

When unsafe patterns are detected, Blaze should provide actionable error messages:

```
Blaze Compilation Error: Component 'form-component' is marked @blaze but contains runtime dependencies.

Found: @csrf directive on line 4
Reason: CSRF tokens must be generated at runtime for security

Solution: Remove @blaze directive or extract CSRF handling to parent component

Component: resources/views/components/form-component.blade.php:1
```

#### Comprehensive pattern detection

Blaze should detect these categories of unsafe patterns:

**Directives:**
- `@csrf`, `@method`
- `@auth`, `@guest`, `@can`, `@cannot`
- `@error`, `@enderror`

**Function calls:**
- `session()`, `request()`, `auth()`
- `old()`, `csrf_token()`, `method_field()`
- `now()`, `today()`, `Carbon::*`

**Variable access:**
- `$errors` (error bag)
- `$user` (if injected by auth middleware)

**Laravel helpers:**
- `url()->current()`, `url()->previous()`
- `route()` with current route parameters

#### Implementation strategy

The detection system should:

1. **Parse component source** during metadata collection phase
2. **Use regex patterns** for common unsafe function calls and directives
3. **Provide specific line numbers** and explanations for each violation
4. **Suggest alternatives** where possible (e.g., move CSRF to parent component)
5. **Allow configuration** to disable certain checks if needed for advanced use cases

This approach prevents developers from accidentally creating broken components while providing clear guidance on how to fix the issues.

### String escaping and other security considerations

[todo: do some experimenting to see show stack traces react.]

### Stack trace preservation

[todo: do some experimenting to see show stack traces react.]
