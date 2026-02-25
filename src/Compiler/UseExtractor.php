<?php

namespace Livewire\Blaze\Compiler;

use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\ParserFactory;

/**
 * Extracts use statements from raw PHP blocks in compiled templates.
 */
class UseExtractor
{
    /**
     * Extract use statements from <?php ?> blocks in the compiled template.
     *
     * Uses php-parser to find the boundary between use statements and code,
     * then splits the original text at that point — no re-printing.
     */
    public function extract(string $compiled, callable $callback): string
    {
        return preg_replace_callback('/<\?php(.*?)\?>|(?<!@)@php(.*?)@endphp/s', function ($match) use ($callback) {
            $isDirective = $match[0][0] === '@';
            $inner = $isDirective ? $match[2] : $match[1];
            $block = '<?php' . $inner;

            $parser = (new ParserFactory)->createForNewestSupportedVersion();

            try {
                $ast = $parser->parse($block);
            } catch (\Throwable) {
                return $match[0];
            }

            if (! $ast) {
                return $match[0];
            }

            $lastUseEnd = null;

            foreach ($ast as $stmt) {
                if (! $stmt instanceof Use_ && ! $stmt instanceof GroupUse) {
                    break;
                }

                $start = $stmt->getStartFilePos();
                $end = $stmt->getEndFilePos();

                $callback(substr($block, $start, $end - $start + 1));

                $lastUseEnd = $end;
            }

            if ($lastUseEnd === null) {
                return $match[0];
            }

            $remaining = ltrim(substr($block, $lastUseEnd + 1));

            if ($remaining === '') {
                return '';
            }

            $open = $isDirective ? '@php ' : '<?php ';
            $close = $isDirective ? '@endphp' : '?>';

            return $open . $remaining . $close;
        }, $compiled);
    }
}
