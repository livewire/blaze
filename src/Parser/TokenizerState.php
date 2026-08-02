<?php

namespace Livewire\Blaze\Parser;

/**
 * Tokenizer FSM states for Blade template lexing.
 */
enum TokenizerState: string
{
    case TEXT = 'TEXT';
    case TAG_OPEN = 'TAG_OPEN';
    case SLOT_OPEN = 'SLOT';
    case TAG_CLOSE = 'TAG_CLOSE';
    case SLOT_CLOSE = 'SLOT_CLOSE';
    case SHORT_SLOT = 'SHORT_SLOT';
}