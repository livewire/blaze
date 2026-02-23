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
        return preg_replace_callback('/<\?php(.*?)\?>/s', function ($match) use ($callback) {
            $block = '<?php' . $match[1];

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

            return '<?php ' . $remaining . '?>';
        }, $compiled);
    }
}
