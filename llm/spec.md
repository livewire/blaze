# Blaze

Blaze is a pre-compiler that massively improves the rendering performance of your app's Blade components.

It uses a tokenized parser to identify static portions of your templates and uses code-folding to remove much of Blade's runtime overhead.

## High-level overview

Blaze hooks into Blade compilation using affordances like `Blade::precompiler(...)` and pre-renders components at compile-time so that they don't even run at runtime.

Here's the general flow:

1. Hook into precompilation
2. Parse template into tokens
3. Assemble tokens into AST (abstract syntax tree)
4. Walk AST and pre-render elligable blade components
5. Assemble AST back into Blade source

Let's dig deeper into each of the above steps.

## Step 1) Hook into precompilation



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

Question: should our tokens be "dumber"? and for example reflect the individual pieces of a tag like attribute name, value, etc... rather than using states to progressively collect more intelligent info about a token and create smarter tokens. It seems fine, but I'm unsure of the tradeoffs.

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

## Step 4) Walk AST and pre-render (fold) elligable blade components

To intelligently fold elligible nodes, we need to walk the AST in two passes. The first is a pre-order traversal (the tree is walked left-to-right meaning parent nodes are encountered before their children). The second is post-order (right-to-left meaning children are encountered before their parents).o

The first, pre-order, walk is used to collect information about each component and which attributes are being passed into them. The second, post-order, walk is used to actually perform the folding.

Here is a high-level summary of the process:

A) Walk tree left to right (parents first, pre-order)
    * Examine source and construct useful metadata about each component
    * Identify deal-breakers in source and metadata that disqualify a component as being unfoldable
B) Walk tree right to left (children first, post-order)
    * Skip unfoldable nodes
    * Fold elligible nodes

Let's first look at the pre-order traversal phase:

### Pre-order traversal

The primary goal during this phase is to collect information about the component and attributes being passed into it.

Here's a list of what we need to learn about a given component:
* A list of attributes being passed in
* Information about the source: filename, filemtime, etc...
* Weather or not the component contains any forbidden, unfoldable, strings (like using `$errors` or `@aware`)

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
<button type="button" class="btn">{{ $btn }}</button>
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
        // If the node was deemed unelligable or there was any kind of error, return the original node...
        return $node
    }
});
```

This process is simple enough and works quite well.

## Step 5) Assemble AST back into Blade source

Now that the AST has been transformed, we can re-assemble it back into Blade source.

The exact algorithm to do so is fairly simple and deterministic, and therefore irrelevant to a writeup like this, but to help you grasp how it all fits together, here's a simplified version of this system's `compile` method:

```php
public function compile(string $content): string
{
    $tokens = $this->tokenizer->tokenize($content);

    $ast = $this->tokenizer->parse($tokens);

    [$ast, $metadata] = $this->walkTreePreOrderAndConstructMetadata($ast);

    $ast = $this->walkTreePostOrderAndFoldEligableComponents($ast, $metadata);

    return $this->tokenizer->render($ast);
}
```

## Finer points

Now that you have a broad overview of how this system works. Let's explore a few of the finer points in detail.

### Precompilation hook timing

In a perfect world Blaze could use the most standard hook for precompilation (as outlined at the beginning of this writeup):

```php
Blade::precompiler(function (string $input) {
    $output = Blaze::compile($input);

    return $output;
});
```

In theory, this would work perfectly for a tool like Blaze, however there are two hurdles:

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

This would normally not be a problem, however, it's compiler is greedy and strips away things like raw php `<?php ... ?>` blocks, causing all sorts of issues for Blaze.

To overcome this hurdle, we need to mutate the array of hooks to ensure Blaze's hooks are the very first hook to be run, even within the `$this->prepareStringsForCompilationUsing` hooks array.

**Hurdle #3: Nested compilation problems**

Consider the following massively-paired-down code snippet:

```php
Blade::prepareStringsForCompilationUsing(function ($input) {
    return $this->foldEligableComponents($input, function ($bladeSourceOfSingleComponent) {
        return Blade::render($bladeSourceOfSingleComponent);
    })
})
```

Because Blaze uses code-folding, it must identify Blade components that can be pre-rendered into the compiled output.

If we render those static components directly inside the compile hook, we end up creating a situtation that Blade is not used to: compiling within an existing compilation.

The problems occur because Blade uses temporary compiler properties/variables to track things like extracted raw blocks, the current path of the compiling view, etc...

Therefore, we end up with lots of bad results when compiling a string within a compilation hook.

There are two strategies around this:

A) Defering Blaze's compilation until after Laravel finishes compiling the file.
B) Creating a sandboxed compilation environment to avoid global compiler property conflicts

_A_ seems possible using a similar strategy to "raw blocks" where we would replace parts of the templates with markers, let Blade finish compiling, then go back and replace the markers.

_B_ is also possible by storing the current value of the global properties, wiping them, precompiling, then replacing all the properties back with the old ones.

B is not ideal because we might end up in situations where a new property is added that we haven't accounted for, causing surprising behavior.

A is not ideal because we may end up in situtaions where markers aren't properly cleaned up and such.

Also A will make the AST manipulation step more complicated because we will have to track markers and replacements and such.

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

To solve this problem, our system must introduce a system for noting all code-folded components and their source filemtimes so that they can be invalidated at runtime.

The solution to this problem requires two additions:
A) A compile-time system to embed folded filemtimes in the compiled view
B) A runtime system to parse and interpret these filemtimes for cache-invalidation

Let's cover each of these systems briefly:

**System A:**

Every time we code-fold a component, we will need to add it's source metadata to a global list. Then when finished transforming the AST and compiling it back into Blade source we will need to embed that metadata into the header of the compiled output like so:

```php
<!-- [Folded]:button:12345674 -->

<h1>Dashboard</h1>

<button type="button" class="btn">{{ $cta }}</button>
```

**System B:**

Unfortunately there is no hook into Blade's compiler at the time of source-file cache invalidation so we need to hack our own using the nearest Blade event:

```php
Event::listen('composing:*', function ($event, $params) use ($invalidator) {
    $view = $params[0];

    if (! $view instanceof \Illuminate\View\View) {
        return;
    }

    // Examine header for folded dependancies and their filemtimes...
    if ($this->hasExpiredFoldedDependency($view)) {
        // Trigger a re-compile of this file if any of it's children are stale...
        $view->getEngine()->getCompiler()->compile($view->getPath());
    }
});
```

### Supporting the @aware directive

As a quick recap, Blade supports a directive called `@aware` that allows a component to be "aware" of distant parent component attributes.

For example, consider this template:

```php
<x-button.group variant="primary">
    <x-button />
</x-button.group>
```

As you can see, the `variant` attribute is being passed into `button.group`. Presumably the button group component uses this attribute/parameter to change the styling of the group.

The `button.group` component might have source code that looks like this:

```php
@props([
    'variant' => null,
])

<div class="{{ $variant === 'primary' ? 'btn-group-primary' : 'btn-group-outline' }}">
    {{ $slot }}
</div>
```

And because `variant="primary"` is passed into it, it will render properly.

However, consider the child `button`'s source looks similarly:

```php
@props([
    'variant' => null,
])

<button type="button" class="{{ $variant === 'primary' ? 'btn-primary' : 'btn-outline' }}">
    {{ $slot }}
</button>
```

Because the `variant="primary"` prop isn't passed into the child as well, it will render in the outline style.

Rather than requiring `variant` to be passed into both components, Blade provides an `@aware` directive that will allow a component to inherit a prop value from a distant parent.

Here's how we can fix the problem in the `button` component source using `@aware`:

```php
@aware(['variant'])

@props([
    'variant' => null,
])

<button type="button" class="{{ $variant === 'primary' ? 'btn-primary' : 'btn-outline' }}">
    {{ $slot }}
</button>
```

Now that `@aware` is at the top, it will look up the component tree at runtime and use the first `variant` prop it finds and use that value.

#### The problem @aware presents

Now that we understand what `@aware` does, we can understand how it might break down in a code-folded scenario.

There are two problems with code-folding and `@aware`:
1) Child components that use `@aware` cannot be code-folded because we can't fully know at compile-time what parent values will be used at runtime.
2) Parent components that have been code-folded no longer provide attributes to children to be aware of

The first problem has a simple solution: don't code-fold components that use `@aware`. There are sophisticated things that could be done to partially make them work, but it may not be worth the complexity. Disqualifying components that use `@aware` is likely the right approach.

The second problem has a slightly more complex solution, but to understand the solution, we have to first understand how Blade implements `@aware` in the first place.

**Understanding @aware's implementation:**

Consider the following template:

```php
<x-button foo="bar">...</x-button>
```

When Blade compiles this template, the compiled output will contain the following two lines of code;

```php
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.button','data' => ['foo' => 'bar']]); ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
```

This tells Laravel's global Blade compiler to "start" a component and push it on to its internal component stack.

Here's the internal source of `startComponent` from Laravel's source so that you can see a glimpse of this system:

```php
public function startComponent($view, array $data = [])
{
    if (ob_start()) {
        $this->componentStack[] = $view;

        $this->componentData[$this->currentComponent()] = $data;

        $this->slots[$this->currentComponent()] = [];
    }
}
```

Now later if a deeply nested component used something like `@aware(['foo'])`, the compiled source of that directive in their source would be:

```php
$foo = $__env->getConsumableComponentData('foo');
```

Here's a glimpse of the `getConsumableComponentData` method in Laravel's source (refactored for brevity):

```php
public function getConsumableComponentData($key)
{
    $currentComponent = count($this->componentStack);

    for ($i = $currentComponent - 1; $i >= 0; $i--) {
        $data = $this->componentData[$i] ?? [];

        if (array_key_exists($key, $data)) {
            return $data[$key];
        }
    }

    return null;
}
```

As you can see, when using `@aware`, Laravel will look up the component data stack until it finds a matching key, then returns and assigns its associated value.

**Re-iterating the problem:**

Now that you understand the aware system better, you can see that if a component is naively folded away it will never have pushed it's data onto the component stack for deeply nested children to consume.

**The solution:**

The solution to this problem is: when folding a component away to append a bit of PHP that soley exists to push any attributes onto the component data stack.

Something like this would suffice:

```php
<?php $__env->pushConsumableComponentData(['foo' => 'bar']); ?>
<?php // Folded parent component.... ?>
<?php $__env->popConsumableComponentData(); ?>
```

Unfortunately, those two methods don't exist in Laravel's source, but we can add a macro to accomplish this:

```php
$this->app->make('view')->macro('pushConsumableComponentData', function ($data) {
    $this->componentStack[] = new \Illuminate\Support\HtmlString('');

    $this->componentData[$this->currentComponent()] = $data;
});

$this->app->make('view')->macro('popConsumableComponentData', function () {
    array_pop($this->componentStack);
});
```

With these macros we can _fake_ a folded component and associated data in the component stack so that deep children can consume the provided attributes at runtime.

This adds slight performance overhead to folded components, but it's fairly neglegible.
