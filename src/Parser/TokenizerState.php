<?php

namespace Livewire\Blaze\Parser;

/**
 * Tokenizer FSM states for Blade template lexing.
 */
enum TokenizerState
{
    case TEXT;
    case TAG_OPEN;
    case SLOT_OPEN;
    case TAG_CLOSE;
    case SLOT_CLOSE;
    case SHORT_SLOT;
    case DIRECTIVE;
}
