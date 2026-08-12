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
 */
class LaravelRegex
{
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
     * Pattern for matching Blade statements that start with "@".
     *
     * @see BladeCompiler::compileStatements() — /\B@(@?\w+(?:::\w+)?)([ \t]*)(\( ( [\S\s]*? ) \))?/x
     */
    const BLADE_STATEMENT = '/^@(@?\w+(?:::\w+)?)([ \t]*)(\( ( [\S\s]*? ) \))?/x';

    /**
     * Pattern for matching component tag attributes.
     *
     * @see ComponentTagCompiler::compileOpeningTags()     — (?<attributes>...)
     * @see ComponentTagCompiler::compileSelfClosingTags() — (?<attributes>...)
     */
    const ATTRIBUTES = "(?<attributes>
        (?:
            \s+
            (?:
                (?:
                    @(?:class)(\( (?: (?>[^()]+) | (?-1) )* \))
                )
                |
                (?:
                    @(?:style)(\( (?: (?>[^()]+) | (?-1) )* \))
                )
                |
                (?:
                    \{\{\s*\\\$attributes(?:[^}]+?)?\s*\}\}
                )
                |
                (?:
                    (\:\\\$)(\w+)
                )
                |
                (?:
                    [\w\-:.@%]+
                    (
                        =
                        (?:
                            \\\"[^\\\"]*\\\"
                            |
                            \'[^\']*\'
                            |
                            [^\'\\\"=<>]+
                        )
                    )?
                )
            )
        )*
        \s*
    )";
}
