<?php

namespace Livewire\Blaze\Optimizations;

class MinifyOutput
{
    public function apply($contents, array $config)
    {
        if (! ($config['optimization']['minify_output'] ?? false)) {
            return $contents;
        }

        // Preserve PHP tags
        $phpBlocks = [];
        $contents = preg_replace_callback('/<\?php.*?\?>/s', function ($match) use (&$phpBlocks) {
            $placeholder = '___PHP_BLOCK_' . count($phpBlocks) . '___';
            $phpBlocks[$placeholder] = $match[0];
            return $placeholder;
        }, $contents);

        // Minify HTML
        $contents = $this->minifyHtml($contents);

        // Restore PHP blocks
        foreach ($phpBlocks as $placeholder => $block) {
            $contents = str_replace($placeholder, $block, $contents);
        }

        return $contents;
    }

    protected function minifyHtml($html)
    {
        // Remove HTML comments (but not IE conditional comments)
        $html = preg_replace('/<!--(?!\[if).*?-->/s', '', $html);
        
        // Remove unnecessary whitespace
        $html = preg_replace('/\s+/', ' ', $html);
        
        // Remove whitespace around tags
        $html = preg_replace('/>\s+</', '><', $html);
        
        // Preserve whitespace in <pre> and <textarea> tags
        $html = preg_replace_callback('/<(pre|textarea)([^>]*)>(.*?)<\/\1>/is', function ($matches) {
            return '<' . $matches[1] . $matches[2] . '>' . $matches[3] . '</' . $matches[1] . '>';
        }, $html);
        
        return trim($html);
    }
}