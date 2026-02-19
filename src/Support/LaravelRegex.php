<?php

namespace Livewire\Blaze\Support;

/**
 * Regex patterns sourced from Laravel's ComponentTagCompiler.
 *
 * Every constant in this class MUST match the corresponding regex
 * in Laravel's source exactly. Do not modify these without first
 * verifying the change against the Laravel source cited in each
 * constant's docblock.
 *
 * @see vendor/laravel/framework/src/Illuminate/View/Compilers/ComponentTagCompiler.php
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
     * @see ComponentTagCompiler::compileSlots() — (\w+(?:-\w+)*)
     */
    const SLOT_INLINE_NAME = '/^\w+(?:-\w+)*/';

    /**
     * Full pattern for matching individual attributes after preprocessing.
     *
     * @see ComponentTagCompiler::getAttributesFromAttributeString() — line 605
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
}
