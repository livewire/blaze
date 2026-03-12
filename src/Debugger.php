<?php

namespace Livewire\Blaze;

use Illuminate\Support\Facades\File;

class Debugger
{
    protected ?int $renderStart = null;

    protected float $renderTime = 0.0;

    protected ?string $timerView = null;

    protected int $bladeComponentCount = 0;

    protected array $bladeComponents = [];

    protected bool $blazeEnabled = false;

    protected bool $timerInjected = false;

    // ── Profiler trace ───────────────────────────
    protected array $traceStack = [];
    protected array $traceEntries = [];
    protected ?float $traceOrigin = null;
    protected ?array $traceDataCache = null;

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
     * Called inside the cache-hit branch of the memoizer output while
     * the entry is still on the trace stack (between startTimer and
     * stopTimer). Re-tags the strategy so memo hits are visible in
     * the strategy breakdown.
     */
    public function recordMemoHit(string $name): void
    {
        if (! empty($this->traceStack)) {
            $this->traceStack[count($this->traceStack) - 1]['strategy'] = 'memo';
        }
    }

    /**
     * Get profiler trace entries and summary data.
     *
     * This is the single source of truth for both the debug bar
     * and the profiler page. Self-times and component summaries
     * are computed here so consumers don't duplicate the work.
     */
    public function getTraceData(): array
    {
        if ($this->traceDataCache !== null) {
            return $this->traceDataCache;
        }

        // Sort entries by start time so the flame chart renders correctly.
        $entries = $this->traceEntries;
        usort($entries, fn ($a, $b) => $a['start'] <=> $b['start']);

        $entries = $this->computeSelfTimes($entries);

        $this->traceDataCache = [
            'entries'       => $entries,
            'totalTime'     => $this->renderTime,
            'components'    => $this->aggregateComponents($entries),
        ];

        return $this->traceDataCache;
    }

    /**
     * Compute self-time for each trace entry.
     *
     * Self-time = entry duration minus sum of direct children's durations.
     * Uses the same stack-based parent assignment as the profiler JS.
     */
    protected function computeSelfTimes(array $entries): array
    {
        if (empty($entries)) {
            return $entries;
        }

        $children = array_fill(0, count($entries), []);
        $stack = [];

        foreach ($entries as $i => $entry) {
            while (! empty($stack) && $entries[end($stack)]['end'] <= $entry['start']) {
                array_pop($stack);
            }

            if (! empty($stack)) {
                $parentIdx = end($stack);

                if ($entry['depth'] === $entries[$parentIdx]['depth'] + 1) {
                    $children[$parentIdx][] = $i;
                }
            }

            $stack[] = $i;
        }

        foreach ($entries as $i => &$entry) {
            $childTime = 0.0;

            foreach ($children[$i] as $ci) {
                $childTime += $entries[$ci]['duration'];
            }

            $entry['selfTime'] = round($entry['duration'] - $childTime, 4);
        }

        return $entries;
    }

    /**
     * Aggregate trace entries into per-component summaries sorted by self-time.
     */
    protected function aggregateComponents(array $entries): array
    {
        $byName = [];

        foreach ($entries as $entry) {
            $name = $entry['name'];

            if (! isset($byName[$name])) {
                $byName[$name] = [
                    'name'      => $name,
                    'count'     => 0,
                    'totalTime' => 0.0,
                    'selfTime'  => 0.0,
                ];
            }

            $byName[$name]['count']++;
            $byName[$name]['totalTime'] += $entry['duration'];
            $byName[$name]['selfTime'] += $entry['selfTime'];
        }

        foreach ($byName as &$comp) {
            $comp['totalTime'] = round($comp['totalTime'], 2);
            $comp['selfTime'] = round($comp['selfTime'], 2);
        }

        usort($byName, fn ($a, $b) => $b['selfTime'] <=> $a['selfTime']);

        return array_values($byName);
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
     * Get all collected data for the profiler and debug bar.
     */
    public function getDebugBarData(): array
    {
        return $this->getData();
    }

    /**
     * Get all collected data for rendering the debug bar.
     *
     * Derives component data from getTraceData() so self-times
     * are computed once and shared with the profiler.
     */
    protected function getData(): array
    {
        $trace = $this->getTraceData();

        // Group flux:icon variants under a single entry for the debug bar.
        $components = collect($trace['components'])
            ->groupBy(fn ($data) => preg_match('/^flux:icon\./', $data['name']) ? 'flux:icon' : $data['name'])
            ->map(fn ($group, $key) => $group->count() > 1
                ? [
                    'name' => $key,
                    'count' => $group->sum('count'),
                    'totalTime' => round($group->sum('totalTime'), 2),
                    'selfTime' => round($group->sum('selfTime'), 2),
                ]
                : $group->first()
            )
            ->sortByDesc('selfTime')
            ->values()
            ->all();

        $strategies = collect($trace['entries'])
            ->countBy(fn ($entry) => $entry['strategy'] ?? 'compiled')
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
            'strategies' => $strategies,
        ];
    }
    
    public function flushState(): void
    {
        $this->renderStart = null;
        $this->renderTime = 0.0;
        $this->timerView = null;
        $this->bladeComponentCount = 0;
        $this->bladeComponents = [];
        $this->blazeEnabled = false;
        $this->timerInjected = false;
        $this->traceStack = [];
        $this->traceEntries = [];
        $this->traceOrigin = null;
        $this->traceDataCache = null;
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
                position: relative;
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
        $accentColor = $isBlaze ? '#FF8602' : '#888888';
        $timeFormatted = $this->formatMs($data['totalTime']);

        $slowestHtml = $this->renderCardSlowest($data);

        $statusLabel = $isBlaze ? 'Blaze On' : 'Blaze Off';

        return <<<HTML
        <div id="blaze-card">
            <button id="blaze-card-close" title="Close" style="position: absolute; top: 14px; right: 14px; background: none; border: none; cursor: pointer; color: #555555; padding: 2px; line-height: 1; font-size: 16px; transition: color 0.15s ease;" onmouseover="this.style.color='#ffffff'" onmouseout="this.style.color='#555555'">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
            </button>

            <div style="color: {$accentColor}; font-weight: 700; font-size: 11px; letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: 4px;">{$statusLabel}</div>
            <span style="color: #ffffff; font-weight: 700; font-size: 26px; letter-spacing: -1.5px; line-height: 1; font-variant-numeric: tabular-nums;">{$timeFormatted}</span>

            {$slowestHtml}

            <a href="/_blaze/profiler" target="_blank" id="blaze-profiler-link" style="display: flex; align-items: center; gap: 6px; margin-top: 12px; padding: 7px 10px; border-radius: 4px; background: rgba(255,134,2,0.08); border: 1px solid rgba(255,134,2,0.15); color: #FF8602; font-size: 11px; font-weight: 600; text-decoration: none; transition: all 0.15s ease; cursor: pointer;" onmouseover="this.style.background='rgba(255,134,2,0.12)';this.style.borderColor='rgba(255,134,2,0.25)'" onmouseout="this.style.background='rgba(255,134,2,0.08)';this.style.borderColor='rgba(255,134,2,0.15)'">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 16l4-8 4 4 4-8"/></svg>
                <span>Open Profiler</span>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left: auto; opacity: 0.5;"><path d="M7 17L17 7"/><path d="M7 7h10v10"/></svg>
            </a>
        </div>
        HTML;
    }

    protected function renderCardSlowest(array $data): string
    {
        $components = $data['components'] ?? [];

        if (empty($components)) {
            return '';
        }

        $top = array_slice($components, 0, 5);

        $rows = '';
        foreach ($top as $component) {
            $name = htmlspecialchars($component['name']);
            $time = $this->formatMs($component['selfTime']);
            $count = $component['count'] > 1
                ? '<span style="color: rgba(255,255,255,0.25); font-size: 9px; margin-left: 3px;">&times;' . $component['count'] . '</span>'
                : '';

            $rows .= '<div style="display: flex; align-items: baseline; justify-content: space-between; gap: 8px;">'
                . '<span style="color: rgba(255,255,255,0.5); font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0;">' . $name . $count . '</span>'
                . '<span style="color: rgba(255,255,255,0.3); font-size: 11px; flex-shrink: 0; font-variant-numeric: tabular-nums;">' . $time . '</span>'
                . '</div>';
        }

        return <<<HTML
        <div style="display: flex; flex-direction: column; gap: 4px; margin-top: 10px;">
            {$rows}
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
