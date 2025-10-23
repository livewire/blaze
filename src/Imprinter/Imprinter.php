<?php

namespace Livewire\Blaze\Imprinter;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Livewire\Blaze\Nodes\ComponentNode;
use Livewire\Blaze\Nodes\Node;
use Livewire\Blaze\Nodes\TextNode;

class Imprinter
{
    protected $componentNameToPath;
    protected $imprintPlaceholders = [];
    protected $cacheDirectory;
    protected $cacheNamespace = 'blaze';

    public function __construct(callable $componentNameToPath)
    {
        $this->componentNameToPath = $componentNameToPath;
        $this->cacheDirectory = storage_path('framework/views/livewire/blaze/components');
        Blade::anonymousComponentPath($this->cacheDirectory, $this->cacheNamespace);
    }

    public function imprint(Node $node): Node
    {
        if (! $node instanceof ComponentNode) {
            return $node;
        }

        $this->capture($node);

        return $node;
    }

    public function restore(Node $node): Node
    {
        if (! $node instanceof TextNode) {
            return $node;
        }

        // Look for IMPRINT_PLACEHOLDER throughout $node->content and capture the full string
        $node->content = preg_replace_callback('/IMPRINT_PLACEHOLDER_\d+/i', function (array $matches) {
            $imprintPlaceholder = $matches[0];

            $key = str()->random(5);

            $output = '';
            $output .= '<'.'?php ';
            $output .= '$blazeImprintData = \Livewire\Blaze\Blaze::imprinter()->getAttributes(\'' . $imprintPlaceholder . '\');';
            $output .= ' extract($blazeImprintData, EXTR_PREFIX_ALL, \'' . $key . '\');';
            $output .= ' unset($blazeImprintData);';
            $output .= ' ?' . '>';

            $imprint = $this->imprintPlaceholders[$imprintPlaceholder];
            $attributes = $imprint['attributes'];
            $content = $imprint['content'];

            foreach ($attributes as $name => $value) {
                $content = preg_replace('/\$'.$name.'(?![a-zA-Z0-9_])/', '\''.$value.'\'', $content);
            }

            $output .= $content;

            return $output;
        }, $node->content);

        return $node;
    }

    public function getAttributes(string $imprintPlaceholder): array
    {
        return $this->imprintPlaceholders[$imprintPlaceholder]['attributes'] ?? [];
    }

    public function getContent(string $imprintPlaceholder): string
    {
        return $this->imprintPlaceholders[$imprintPlaceholder]['content'];
    }

    public function storeAttributes(string $imprintPlaceholder, array $attributes): void
    {
        $this->imprintPlaceholders[$imprintPlaceholder]['attributes'] = $attributes;
    }

    protected function capture(ComponentNode $node): void
    {
        $componentPath = ($this->componentNameToPath)($node->name);

        if (empty($componentPath) || ! file_exists($componentPath)) {
            return;
        }

        $source = file_get_contents($componentPath);

        preg_match_all('/(\s*)@imprint\((.*?)\)(.*?)@endimprint/s', $source, $matches);

        if (empty($matches[0])) {
            return;
        }

        $modifiedSource = $source;

        foreach ($matches[0] as $index => $match) {
            $imprintBlock = $matches[0][$index];
            $whitespace = $matches[1][$index];
            $attributes = $matches[2][$index];
            $content = $matches[3][$index];

            $placeholder = 'IMPRINT_PLACEHOLDER_' . $index;

            $output = $whitespace;
            $output .= '<'.'?php \Livewire\Blaze\Blaze::imprinter()->storeAttributes(\'' . $placeholder . '\', ' . $attributes . '); ?'.'>';
            $output .= $placeholder;

            $modifiedSource = str_replace($imprintBlock, $output, $modifiedSource);

            $this->imprintPlaceholders[$placeholder] = [
                'attributes' => [],
                'content' => $content,
            ];
        }

        $name = $node->name;
        $path = str_replace('.', '/', $name);

        $directory = $this->cacheDirectory . '/' . str($path)->beforeLast('/');
        $filename = str($path)->afterLast('/')->value() . '.blade.php';

        File::ensureDirectoryExists($directory);

        File::put($directory . '/' . $filename, $modifiedSource);

        $node->name = $this->cacheNamespace . '::' . $name;
    }
}
