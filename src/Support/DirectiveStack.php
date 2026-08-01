<?php

namespace Livewire\Blaze\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DirectiveStack
{
    /** @var DirectiveStructure[] */
    protected array $stack = [];

    /** @var DirectiveStructure[] */
    protected array $structures = [];

    public function __construct(array $conditions = [])
    {
        $this->structures = [
            // Blade
            new DirectiveStructure(['if']),
            new DirectiveStructure(['unless'], ['endif']),
            new DirectiveStructure(['isset'], ['endif']),
            new DirectiveStructure(['empty'], ['endif']),
            new DirectiveStructure(['auth'], ['endif']),
            new DirectiveStructure(['guest'], ['endif']),
            new DirectiveStructure(['env'], ['endif']),
            new DirectiveStructure(['production'], ['endif']),
            new DirectiveStructure(['once'], ['endif']),
            new DirectiveStructure(['can'], ['endif']),
            new DirectiveStructure(['cannot'], ['endif']),
            new DirectiveStructure(['canany'], ['endif']),
            new DirectiveStructure(['hassection', 'hasstack', 'sectionmissing'], ['endif']),
            new DirectiveStructure(['switch']),
            new DirectiveStructure(['pushif']),
            new DirectiveStructure(['for']),
            new DirectiveStructure(['foreach']),
            new DirectiveStructure(['forelse']),
            new DirectiveStructure(['while']),
            new DirectiveStructure(['error']),
            new DirectiveStructure(['session']),
            new DirectiveStructure(['context']),
            new DirectiveStructure(['fragment']),
            new DirectiveStructure(['section'], ['show', 'append', 'overwrite', 'stop']),
            new DirectiveStructure(['push', 'pushonce']),
            new DirectiveStructure(['prepend', 'prependonce']),
            new DirectiveStructure(['lang']),
            new DirectiveStructure(['component'], ['endcomponentclass']),
            new DirectiveStructure(['componentfirst']),
            new DirectiveStructure(['slot']),

            // Livewire
            new DirectiveStructure(['script']),
            new DirectiveStructure(['assets']),
            new DirectiveStructure(['island']),
            new DirectiveStructure(['teleport']),
            new DirectiveStructure(['persist']),
            new DirectiveStructure(['placeholder']),
        ];

        foreach ($conditions as $condition) {
            $condition = Str::lower($condition);

            $this->structures[] = new DirectiveStructure([$condition], ['endif']);
            $this->structures[] = new DirectiveStructure(['unless'.$condition], ['end'.$condition, 'endif']);
        }
    }

    /**
     * Create a new directive stack.
     */
    public static function make(array $conditions = []): static
    {
        return new static($conditions);
    }

    /**
     * Add a directive to the stack, opening or closing its matching structure.
     */
    public function add(string $name): void
    {
        $name = Str::lower($name);

        for ($index = count($this->stack) - 1; $index >= 0; $index--) {
            if ($this->stack[$index]->closesWith($name)) {
                $this->stack = Arr::take($this->stack, $index);

                return;
            }
        }

        foreach ($this->structures as $structure) {
            if ($structure->opensWith($name)) {
                $this->stack[] = $structure;

                return;
            }
        }
    }

    /**
     * Determine whether any directive structure is currently open.
     */
    public function open(): bool
    {
        return $this->stack !== [];
    }
}
