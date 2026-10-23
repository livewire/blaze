@blaze(fold: true, safe: ['key'])

@props(['label' => __('Save & close'), 'key' => 'Save & close'])

<button aria-label="{{ __('Save & close') }}" data-raw="{!! __('Save & close') !!}">
    {{ $label }}
    {{ __($key) }}
    {{ trans_choice('apples', 2) }}
    {{ Lang::string('Save & close') }}
    @lang('Save & close')
    <x-foldable.translated-child content="{{ __('Save & close') }}" />
</button>
