# Upgrading from Blaze 1.0 to Blaze 2.0

## Breaking change: `@blaze` no longer enables folding or memoization by default

In Blaze 1.0, `@blaze` implicitly enabled both folding and memoization:

```php
// Blaze 1.0 — this enabled folding + memoization
@blaze
```

In Blaze 2.0, `@blaze` enables **function compilation only**:

```php
// Blaze 2.0 — this enables function compilation
@blaze
```

To enable folding or memoization, you now need to opt in explicitly:

```php
@blaze(fold: true, memo: true)
```
