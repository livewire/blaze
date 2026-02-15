<?php

namespace Livewire\Blaze\Parser;

/**
 * Tokenizer FSM states for Blade template lexing.
 */
enum TokenizerState: string
{
    case TEXT = 'TEXT';
    case TAG_OPEN = 'TAG_OPEN';
    case TAG_NAME = 'TAG_NAME';
    case ATTRIBUTE_NAME = 'ATTRIBUTE_NAME';
    case ATTRIBUTE_VALUE = 'ATTRIBUTE_VALUE';
    case SLOT = 'SLOT';
    case SLOT_NAME = 'SLOT_NAME';
    case TAG_CLOSE = 'TAG_CLOSE';
    case SLOT_CLOSE = 'SLOT_CLOSE';
    case SHORT_SLOT = 'SHORT_SLOT';
}