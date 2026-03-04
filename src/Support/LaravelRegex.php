<?php

namespace Livewire\Blaze\Support;

/**
 * Regex patterns sourced from Laravel's view compiler (ComponentTagCompiler, BladeCompiler).
 *
 * Every constant in this class MUST match the corresponding regex
 * in Laravel's source exactly. Do not modify these without first
 * verifying the change against the Laravel source cited in each
 * constant's docblock.
 *
 * @see vendor/laravel/framework/src/Illuminate/View/Compilers/ComponentTagCompiler.php
 * @see vendor/laravel/framework/src/Illuminate/View/Compilers/BladeCompiler.php
 * @see vendor/laravel/framework/src/Illuminate/View/Compilers/Concerns/CompilesComments.php
 */
class LaravelRegex
{
    /**
     * Pattern for matching a component tag name at the current position.
     *
     * @see ComponentTagCompiler::compileOpeningTags()     — x[-\:]([\w\-\:\.]*)
     * @see ComponentTagCompiler::compileSelfClosingTags() — x[-\:]([\w\-\:\.]*)
     * @see ComponentTagCompiler::compileClosingTags()     — x[-\:][\w\-\:\.]*
     */
    const TAG_NAME = '/^[\w\-\:\.]*/';

    /**
     * Pattern for matching a slot inline name (e.g., <x-slot:header>).
     *
     * @see ComponentTagCompiler::compileSlots() — line 522, (?:\:(?<inlineName>\w+(?:-\w+)*))?
     */
    const SLOT_INLINE_NAME = '/^\w+(?:-\w+)*/';

    /**
     * Pattern for matching individual attributes after preprocessing.
     *
     * @see ComponentTagCompiler::getAttributesFromAttributeString() — lines 605-619
     */
    const ATTRIBUTE_PATTERN = '/
        (?<attribute>[\w\-:.@%]+)
        (
            =
            (?<value>
                (
                    \"[^\"]+\"
                    |
                    \\\'[^\\\']+\\\'
                    |
                    [^\s>]+
                )
            )
        )?
    /x';

    /**
     * Pattern for matching Blade comments ({{-- ... --}}).
     *
     * @see CompilesComments::compileComments() — sprintf('/%s--(.*?)--%s/s', contentTags)
     */
    const BLADE_COMMENT = '/\{\{--(.*?)--\}\}/s';

    /**
     * Pattern for matching @verbatim...@endverbatim blocks.
     *
     * @see BladeCompiler::storeVerbatimBlocks() — /(?<!@)@verbatim(\s*)(.*?)@endverbatim/s
     */
    const VERBATIM_BLOCK = '/(?<!@)@verbatim(\s*)(.*?)@endverbatim/s';

    /**
     * Pattern for matching @php...@endphp blocks.
     *
     * @see BladeCompiler::storePhpBlocks() — /(?<!@)@php(.*?)@endphp/s
     */
    const PHP_BLOCK = '/(?<!@)@php(.*?)@endphp/s';
}
