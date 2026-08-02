# Upgrading from 1.0-beta to 1.0

## Breaking change: `@blaze` no longer enables folding or memoization by default

In Blaze 1.0-beta, `@blaze` implicitly enabled both folding and memoization:

```php
@blaze // Before: folding + memoization
```

In Blaze 1.0, `@blaze` enables **function compilation only**:

```php
@blaze // Now: function compilation only
```

To enable folding or memoization, you now need to opt in explicitly:

```php
@blaze(fold: true, memo: true)
```

or using the `Blaze::optimize()` in your service provider:

```php
Blaze::optimize()
    ->in(resource_path('views/components'))
    ->in(resource_path('views/components/ui'), fold: true)
    ->in(resource_path('views/components/icons'), memo: true);
```

Only use these strategies if you understand their limitations. To learn more, read the [Optimization strategies](README.md#optimization-strategies) section in the README.