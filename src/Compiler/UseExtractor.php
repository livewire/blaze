<?php

namespace Livewire\Blaze\Compiler;

use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Parser;

/**
 * Extracts use statements from raw PHP blocks in compiled templates.
 */
class UseExtractor
{
    protected Parser $parser;

    public function __construct()
    {
        $this->parser = app(Parser::class);
    }

    /**
     * Extract use statements from <?php ?> blocks in the compiled template.
     *
     * Uses php-parser to find the boundary between use statements and code,
     * then splits the original text at that point — no re-printing.
     */
    public function extract(string $php, callable $callback): string
    {
        try {
            $ast = $this->parser->parse($php);
        } catch (\Throwable) {
            return $php;
        }

        if (! $ast) {
            return $php;
        }

        $lastUseEnd = null;

        foreach ($ast as $stmt) {
            if (! $stmt instanceof Use_ && ! $stmt instanceof GroupUse) {
                break;
            }

            $start = $stmt->getStartFilePos();
            $end = $stmt->getEndFilePos();

            $callback(substr($php, $start, $end - $start + 1));

            $lastUseEnd = $end;
        }

        if ($lastUseEnd === null) {
            return $php;
        }

        $remaining = ltrim(substr($php, $lastUseEnd + 1));

        if (! $remaining) {
            return '';
        }
        
        return '<?php ' . $remaining . '?>';
    }
}
