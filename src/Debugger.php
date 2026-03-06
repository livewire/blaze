<?php

namespace Livewire\Blaze;

use Illuminate\Support\Facades\File;

class Debugger
{
    protected ?int $renderStart = null;

    protected float $renderTime = 0.0;

    protected ?string $timerView = null;

    protected array $components = [];

    protected int $bladeComponentCount = 0;

    protected array $bladeComponents = [];

    protected bool $blazeEnabled = false;

    protected bool $timerInjected = false;

    // ── Profiler trace ───────────────────────────
    protected array $traceStack = [];
    protected array $traceEntries = [];
    protected ?float $traceOrigin = null;
    protected int $memoHits = 0;
    protected array $memoHitNames = [];

    public readonly DebuggerStore $store;

    public function __construct(
        protected BladeService $blade,
    ) {
        $this->store = new DebuggerStore;
    }

    /**
     * Extract a human-readable component name from its file path.
     *
     * Handles various path patterns:
     * - Flux views: .../resources/views/flux/button/index.blade.php -> flux:button
     * - Vendor packages: .../vendor/livewire/flux/resources/views/components/... -> flux:...
     * - App components: .../resources/views/components/button.blade.php -> button
     */
    public function extractComponentName(string $path): string
    {
        $resolved = realpath($path) ?: $path;

        // Flux views pattern: .../resources/views/flux/<component>.blade.php
        if (preg_match('#/resources/views/flux/(.+?)\.blade\.php$#', $resolved, $matches)) {
            $name = str_replace('/', '.', $matches[1]);
            $name = preg_replace('/\.index$/', '', $name);
            return 'flux:'.$name;
        }

        // Standard components/ directory
        if (preg_match('#/resources/views/components/(.+?)\.blade\.php$#', $resolved, $matches)) {
            $name = str_replace('/', '.', $matches[1]);
            $name = preg_replace('/\.index$/', '', $name);

            // Detect package namespace from vendor path
            if (preg_match('#/vendor/[^/]+/([^/]+)/#', $resolved, $vendorMatches)) {
                $package = $vendorMatches[1];
                if ($package !== 'blaze') {
                    return $package.':'.$name;
                }
            }

            return 'x-'.$name;
        }

        // Fallback: use the filename without .blade
        $filename = pathinfo($resolved, PATHINFO_FILENAME);
        return str_replace('.blade', '', $filename);
    }

    /**
     * Start a timer for a component render.
     *
     * Called at the component call site (wrapping the entire render including
     * initialization). Name and strategy are injected at compile time by the
     * Instrumenter so there's no hash indirection at runtime.
     */
    public function startTimer(string $name, string $strategy = 'blade', ?string $file = null): void
    {
        $now = hrtime(true);

        if ($this->traceOrigin === null) {
            $this->traceOrigin = $now;
        }

        $entry = [
            'name'     => $name,
            'start'    => ($now - $this->traceOrigin) / 1e6, // ms from origin
            'depth'    => count($this->traceStack),
            'children' => 0,
            'strategy' => $strategy,
        ];

        if ($file !== null) {
            $entry['file'] = $file;
        }

        $this->traceStack[] = $entry;
    }

    /**
     * Stop the most recent component timer and record the result.
     */
    public function stopTimer(string $name): void
    {
        if (empty($this->traceStack)) {
            return;
        }

        $now = hrtime(true);

        $entry = array_pop($this->traceStack);
        $entry['end']      = ($now - $this->traceOrigin) / 1e6;
        $entry['duration'] = $entry['end'] - $entry['start'];

        if (! empty($this->traceStack)) {
            $this->traceStack[count($this->traceStack) - 1]['children']++;
        }

        $this->traceEntries[] = $entry;

        // Also feed the debug bar.
        $this->recordComponent($entry['name'], $entry['duration'] / 1000);
    }

    /**
     * Resolve a human-readable view name at runtime.
     *
     * Used for Livewire/Volt views where the Blade compiler path is a
     * hash-named cache file. Falls back to null so the caller can use
     * the hash filename as a last resort.
     */
    public function resolveViewName(): ?string
    {
        $livewire = app('view')->shared('__livewire');

        if ($livewire && method_exists($livewire, 'getName')) {
            return $livewire->getName();
        }

        return null;
    }

    /**
     * Record a memoization cache hit (component skipped rendering).
     *
     * This is called inside the cache-hit branch of the memoizer output,
     * while the entry is still on the trace stack (between startTimer and
     * stopTimer). We change its strategy from 'compiled' to 'memo'
     * so the profiler can visually distinguish cache hits from misses.
     */
    public function recordMemoHit(string $name): void
    {
        $this->memoHits++;

        if (! isset($this->memoHitNames[$name])) {
            $this->memoHitNames[$name] = 0;
        }

        $this->memoHitNames[$name]++;

        // Re-tag the current trace entry so the profiler shows it as a hit.
        if (! empty($this->traceStack)) {
            $this->traceStack[count($this->traceStack) - 1]['strategy'] = 'memo';
        }
    }

    /**
     * Get profiler trace entries and summary data for the profiler page.
     */
    public function getTraceData(): array
    {
        // Sort entries by start time so the flame chart renders correctly.
        $entries = $this->traceEntries;
        usort($entries, fn ($a, $b) => $a['start'] <=> $b['start']);

        return [
            'entries'       => $entries,
            'totalTime'     => $this->renderTime,
            'memoHits'     => $this->memoHits,
            'memoHitNames' => $this->memoHitNames,
        ];
    }

    public function setTimerView(string $name): void
    {
        $this->timerView = $name;
    }

    /**
     * Inject start/stop timer calls into the compiled file of the first
     * view being rendered. This ensures we measure only view rendering
     * time, not the full request lifecycle.
     *
     * Called from the view composer on each composing view; only the
     * first successful injection per request takes effect.
     */
    public function injectRenderTimer(\Illuminate\View\View $view): void
    {
        if ($this->timerInjected) {
            return;
        }

        if (request()->hasHeader('X-Livewire') || str_starts_with($view->getName(), 'errors::')) {
            // Prevent timer being injected into Livewire views,
            // error pages and prevent any further checks...
            $this->timerInjected = true;

            return;
        }

        $path = $view->getPath();

        // Some views (e.g. Livewire virtual views) may not have a real path.
        if (! $path || ! file_exists($path)) {
            return;
        }

        // Claim the flag early to prevent re-entrant calls (the
        // compile() below can trigger nested view compositions).
        $this->timerInjected = true;

        // Ensure the view is compiled.
        if ($this->blade->compiler->isExpired($path)) {
            $this->blade->compiler->compile($path);
        }

        $compiledPath = $this->blade->compiler->getCompiledPath($path);

        if (! file_exists($compiledPath)) {
            return;
        }

        $compiled = file_get_contents($compiledPath);

        // Record which view was wrapped with the render timer.
        $this->setTimerView($this->resolveTimerViewName($view));

        // Already injected (persisted from a previous request).
        if (str_contains($compiled, '__blaze_timer')) {
            return;
        }

        $start = '<?php $__blaze->debugger->startRenderTimer(); /* __blaze_timer */ ?>';
        $stop = '<?php $__blaze->debugger->stopRenderTimer(); ?>';

        File::replace($compiledPath, $start . $compiled . $stop);
    }

    /**
     * Resolve a human-readable name for the view being timed.
     *
     * For Livewire SFCs the view path points to an extracted blade file
     * (e.g. storage/.../livewire/views/6ea59dbe.blade.php) which isn't
     * meaningful. In that case we pull the component name from Livewire's
     * shared view data instead.
     */
    protected function resolveTimerViewName(\Illuminate\View\View $view): string
    {
        $path = $view->getPath();

        // Livewire SFC extracted views live inside a "livewire/views" cache directory.
        if ($path && str_contains($path, '/livewire/views/')) {
            $livewire = app('view')->shared('__livewire');

            if ($livewire && method_exists($livewire, 'getName')) {
                return $livewire->getName();
            }
        }

        return $view->name();
    }

    public function startRenderTimer(): void
    {
        $this->renderStart = hrtime(true);
    }

    public function stopRenderTimer(): void
    {
        if ($this->renderStart !== null) {
            $this->renderTime = (hrtime(true) - $this->renderStart) / 1e6; // ns → ms
        }
    }

    public function setBlazeEnabled(bool $enabled): void
    {
        $this->blazeEnabled = $enabled;
    }

    public function incrementBladeComponents(string $name = 'unknown'): void
    {
        $this->bladeComponentCount++;

        if (! isset($this->bladeComponents[$name])) {
            $this->bladeComponents[$name] = 0;
        }

        $this->bladeComponents[$name]++;
    }

    /**
     * Record a component render for the debug bar.
     */
    protected function recordComponent(string $name, float $durationSeconds): void
    {
        if (! isset($this->components[$name])) {
            $this->components[$name] = [
                'name' => $name,
                'count' => 0,
                'totalTime' => 0.0,
            ];
        }

        $this->components[$name]['count']++;
        $this->components[$name]['totalTime'] += $durationSeconds;
    }

    /**
     * Get all collected data for the profiler and debug bar.
     */
    public function getDebugBarData(): array
    {
        return $this->getData();
    }

    /**
     * Get all collected data for rendering the debug bar.
     */
    protected function getData(): array
    {
        $components = collect($this->components)
            ->map(fn ($data) => [
                'name' => $data['name'],
                'count' => $data['count'],
                'totalTime' => round($data['totalTime'] * 1000, 2), // ms
            ])
            ->groupBy(fn ($data) => preg_match('/^flux:icon\./', $data['name']) ? 'flux:icon' : $data['name'])
            ->map(fn ($group, $key) => $group->count() > 1
                ? [
                    'name' => $key,
                    'count' => $group->sum('count'),
                    'totalTime' => round($group->sum('totalTime'), 2),
                ]
                : $group->first()
            )
            ->sortByDesc('totalTime')
            ->values()
            ->all();

        return [
            'blazeEnabled' => $this->blazeEnabled,
            'totalTime' => round($this->renderTime, 2),
            'totalComponents' => array_sum(array_column($components, 'count')),
            'bladeComponentCount' => $this->bladeComponentCount,
            'bladeComponents' => collect($this->bladeComponents)
                ->map(fn ($count, $name) => ['name' => $name, 'count' => $count])
                ->sortByDesc('count')
                ->values()
                ->all(),
            'components' => $components,
            'timerView' => $this->timerView,
        ];
    }
    
    public function flushState(): void
    {
        $this->renderStart = null;
        $this->renderTime = 0.0;
        $this->timerView = null;
        $this->components = [];
        $this->bladeComponentCount = 0;
        $this->bladeComponents = [];
        $this->blazeEnabled = false;
        $this->timerInjected = false;
        $this->traceStack = [];
        $this->traceEntries = [];
        $this->traceOrigin = null;
        $this->memoHits = 0;
        $this->memoHitNames = [];
    }

    protected function formatMs(float $value): string
    {
        if ($value >= 1000) {
            return round($value / 1000, 2) . 's';
        }

        if ($value < 0.01 && $value > 0) {
            return round($value * 1000, 2) . 'μs';
        }

        return round($value, 2) . 'ms';
    }

    // ──────────────────────────────────────────
    //  Rendering
    // ──────────────────────────────────────────

    /**
     * Render the debug bar as an HTML string to be injected into the page.
     */
    public function render(): string
    {
        $data = $this->getData();

        return implode("\n", [
            '<!-- Blaze Debug Bar -->',
            $this->renderStyles($data),
            $this->renderHtml($data),
            $this->renderScript(),
            '<!-- End Blaze Debug Bar -->',
        ]);
    }

    protected function renderStyles(array $data): string
    {
        return <<<HTML
        <style>
            #blaze-debugbar *, #blaze-debugbar *::before, #blaze-debugbar *::after {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }

            #blaze-debugbar {
                position: fixed;
                bottom: 20px;
                right: 20px;
                z-index: 99999;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                gap: 12px;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }

            #blaze-card {
                background: #0b0809;
                border: 1px solid #1b1b1b;
                border-radius: 14px;
                padding: 20px 20px 24px;
                min-width: 280px;
                max-width: 340px;
                box-shadow: 0 4px 24px rgba(0, 0, 0, 0.6);
                animation: blaze-card-in 0.25s ease-out;
                transform-origin: bottom right;
            }

            @keyframes blaze-card-in {
                from { opacity: 0; transform: translateY(6px); }
                to { opacity: 1; transform: translateY(0); }
            }

        </style>
        HTML;
    }

    protected function renderHtml(array $data): string
    {
        return <<<HTML
        <div id="blaze-debugbar">
            {$this->renderCard($data)}
        </div>
        HTML;
    }

    protected function renderCard(array $data): string
    {
        $isBlaze = $data['blazeEnabled'];
        $accentColor = $isBlaze ? '#FF8602' : '#6366f1';
        $modeName = $isBlaze ? 'Blaze' : 'Blade';
        $timeFormatted = $this->formatMs($data['totalTime']);

        $timerViewHtml = '';
        if ($data['timerView']) {
            $viewName = htmlspecialchars($data['timerView']);
            $timerViewHtml = '<div style="color: rgba(255,255,255,0.3); font-size: 10px; margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' . $viewName . '</div>';
        }

        return <<<HTML
        <div id="blaze-card">
            <div style="display: flex; align-items: center; gap: 7px; margin-bottom: 4px;">
                <span style="color: {$accentColor}; font-weight: 700; font-size: 11px; letter-spacing: 0.05em; text-transform: uppercase;">{$modeName}</span>
                <button id="blaze-card-close" title="Close" style="margin-left: auto; background: none; border: none; cursor: pointer; color: #555555; padding: 2px; line-height: 1; font-size: 16px; transition: color 0.15s ease;" onmouseover="this.style.color='#ffffff'" onmouseout="this.style.color='#555555'">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                </button>
            </div>

            <div style="display: flex; align-items: baseline; gap: 8px;">
                <span style="color: #ffffff; font-weight: 700; font-size: 26px; letter-spacing: -1.5px; line-height: 1; font-variant-numeric: tabular-nums;">{$timeFormatted}</span>
            </div>

            {$timerViewHtml}

            <a href="/_blaze/profiler" target="_blank" id="blaze-profiler-link" style="display: flex; align-items: center; gap: 6px; margin-top: 10px; padding: 7px 10px; border-radius: 4px; background: rgba(255,134,2,0.08); border: 1px solid rgba(255,134,2,0.15); color: #FF8602; font-size: 11px; font-weight: 600; text-decoration: none; transition: all 0.15s ease; cursor: pointer;" onmouseover="this.style.background='rgba(255,134,2,0.12)';this.style.borderColor='rgba(255,134,2,0.25)'" onmouseout="this.style.background='rgba(255,134,2,0.08)';this.style.borderColor='rgba(255,134,2,0.15)'">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 16l4-8 4 4 4-8"/></svg>
                <span>Open Profiler</span>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left: auto; opacity: 0.5;"><path d="M7 17L17 7"/><path d="M7 7h10v10"/></svg>
            </a>
        </div>
        HTML;
    }

    protected function renderScript(): string
    {
        return <<<HTML
        <script>
        (function() {
            var card = document.getElementById('blaze-card');
            var closeBtn = document.getElementById('blaze-card-close');

            if (closeBtn && card) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    card.style.display = 'none';
                });
            }
        })();
        </script>
        HTML;
    }
}
