@blaze(fold: true, safe: ['errorLabel'])

@props(['errorLabel'])

<flux:input label="Email" :error:aria-label="$errorLabel" />
