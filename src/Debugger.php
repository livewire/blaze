<?php

namespace Livewire\Blaze;

class Debugger
{
    protected ?int $renderStart = null;

    protected float $renderTime = 0.0;

    protected ?string $timerView = null;

    protected array $components = [];

    protected int $bladeComponentCount = 0;

    protected array $bladeComponents = [];

    protected bool $blazeEnabled = false;

    protected ?array $comparison = null;

    protected bool $isColdRender = false;

    // ── Profiler trace ───────────────────────────
    protected array $traceStack = [];
    protected array $traceEntries = [];
    protected ?float $traceOrigin = null;
    protected int $memoHits = 0;
    protected array $memoHitNames = [];

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
     */
    public function recordMemoHit(string $name): void
    {
        $this->memoHits++;

        if (! isset($this->memoHitNames[$name])) {
            $this->memoHitNames[$name] = 0;
        }

        $this->memoHitNames[$name]++;
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
            'entries'      => $entries,
            'totalTime'    => $this->renderTime,
            'memoHits'     => $this->memoHits,
            'memoHitNames' => $this->memoHitNames,
        ];
    }

    public function setTimerView(string $name): void
    {
        $this->timerView = $name;
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

    public function getPageRenderTime(): float
    {
        return $this->renderTime;
    }

    public function setBlazeEnabled(bool $enabled): void
    {
        $this->blazeEnabled = $enabled;
    }

    public function setComparison(?array $comparison): void
    {
        $this->comparison = $comparison;
    }

    public function setIsColdRender(bool $cold): void
    {
        $this->isColdRender = $cold;
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
            'isColdRender' => $this->isColdRender,
            'comparison' => $this->comparison,
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

    /**
     * Format a number for display (e.g. 83120 -> "83.12k").
     */
    protected function formatCount(int|float $value): string
    {
        if ($value >= 1000) {
            return round($value / 1000, 2) . 'k';
        }

        return (string) $value;
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
        $accentRgb = $data['blazeEnabled'] ? '255, 134, 2' : '99, 102, 241';

        return <<<HTML
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500;600;700;800;900&display=swap');

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
                font-family: "Roboto Mono", ui-monospace, SFMono-Regular, monospace;
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                gap: 12px;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }

            #blaze-bubble {
                width: 48px;
                height: 48px;
                border-radius: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                text-decoration: none;
                transition: transform 0.15s ease, box-shadow 0.2s ease;
                position: relative;
                flex-shrink: 0;
            }

            #blaze-bubble:hover { transform: scale(1.05); }
            #blaze-bubble:active { transform: scale(0.95); transition-duration: 0.1s; }

            #blaze-card {
                background: #000000;
                border: 1px solid #1b1b1b;
                border-radius: 6px;
                padding: 16px;
                min-width: 280px;
                max-width: 340px;
                box-shadow: 0 4px 24px rgba(0, 0, 0, 0.6);
                animation: blaze-card-in 0.25s ease-out;
                transform-origin: bottom right;
            }

            #blaze-detail-panel {
                max-height: 240px;
                overflow-y: auto;
                scrollbar-width: thin;
                scrollbar-color: #1b1b1b transparent;
            }

            #blaze-detail-panel::-webkit-scrollbar { width: 4px; }
            #blaze-detail-panel::-webkit-scrollbar-track { background: transparent; }
            #blaze-detail-panel::-webkit-scrollbar-thumb { background: #1b1b1b; border-radius: 2px; }

            @keyframes blaze-card-in {
                from { opacity: 0; transform: translateY(6px); }
                to { opacity: 1; transform: translateY(0); }
            }

            @keyframes blaze-pulse {
                0%, 100% { box-shadow: 0 2px 12px rgba({$accentRgb}, 0.3); }
                50% { box-shadow: 0 2px 16px rgba({$accentRgb}, 0.5), 0 0 0 4px rgba({$accentRgb}, 0.06); }
            }

            @keyframes blaze-savings-in {
                0% { opacity: 0; transform: translateY(4px); }
                100% { opacity: 1; transform: translateY(0); }
            }

            #blaze-detail-toggle { transition: color 0.15s ease; }
            #blaze-detail-toggle:hover { color: #ffffff !important; }
        </style>
        HTML;
    }

    protected function renderHtml(array $data): string
    {
        return <<<HTML
        <div id="blaze-debugbar">
            {$this->renderCard($data)}
            {$this->renderBubble($data)}
        </div>
        HTML;
    }

    protected function renderCard(array $data): string
    {
        $isBlaze = $data['blazeEnabled'];
        $accentColor = $isBlaze ? '#FF8602' : '#6366f1';
        $modeName = $isBlaze ? 'Blaze' : 'Blade';
        $timeFormatted = $this->formatMs($data['totalTime']);
        $totalComponents = $isBlaze ? $data['totalComponents'] : $data['bladeComponentCount'];
        $componentsFormatted = $this->formatCount($totalComponents);

        $coldTag = $data['isColdRender']
            ? ' <span style="color: rgba(255,255,255,0.3); font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; background: rgba(255,255,255,0.03); border: 1px solid #1b1b1b; padding: 3px 6px; border-radius: 2px; line-height: 1;">cold</span>'
            : '';

        $timerViewHtml = '';
        if ($data['timerView']) {
            $viewName = htmlspecialchars($data['timerView']);
            $timerViewHtml = '<div style="color: rgba(255,255,255,0.3); font-size: 10px; margin-top: 4px; font-family: inherit; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' . $viewName . '</div>';
        }

        $savingsHtml = $this->renderSavingsBlock($data);
        $detailHtml = $this->renderComponentDetail($data);

        return <<<HTML
        <div id="blaze-card">
            <div style="display: flex; align-items: center; gap: 7px; margin-bottom: 4px;">
                <span style="color: {$accentColor}; font-weight: 700; font-size: 11px; letter-spacing: 0.05em; text-transform: uppercase;">{$modeName}</span>
                <button id="blaze-card-close" title="Close" style="margin-left: auto; background: none; border: none; cursor: pointer; color: rgba(255,255,255,0.3); padding: 2px; line-height: 1; font-size: 16px; transition: color 0.15s ease;" onmouseover="this.style.color='#ffffff'" onmouseout="this.style.color='rgba(255,255,255,0.3)'">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                </button>
            </div>

            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="color: #ffffff; font-weight: 700; font-size: 26px; font-family: inherit; letter-spacing: -1.5px; line-height: 1; font-variant-numeric: tabular-nums;">{$timeFormatted}</span>
                {$coldTag}
            </div>

            {$timerViewHtml}

            {$savingsHtml}

            <div style="margin-top: 8px; padding: 4px 0;">
                <div id="blaze-detail-toggle" style="color: rgba(255,255,255,0.5); font-size: 11px; cursor: pointer; user-select: none; display: flex; align-items: center; gap: 5px; font-weight: 500;">
                    <span id="blaze-detail-arrow" style="font-size: 8px; transition: transform 0.2s ease; display: inline-block;">&#9654;</span>
                    <span>{$componentsFormatted} components</span>
                </div>
                <div id="blaze-detail-panel" style="display: none; margin-top: 10px;">
                    {$detailHtml}
                </div>
            </div>

            <a href="/_blaze/profiler" target="_blank" id="blaze-profiler-link" style="display: flex; align-items: center; gap: 6px; margin-top: 10px; padding: 7px 10px; border-radius: 4px; background: rgba(255,134,2,0.08); border: 1px solid rgba(255,134,2,0.15); color: #FF8602; font-size: 11px; font-weight: 600; text-decoration: none; transition: all 0.15s ease; cursor: pointer;" onmouseover="this.style.background='rgba(255,134,2,0.12)';this.style.borderColor='rgba(255,134,2,0.25)'" onmouseout="this.style.background='rgba(255,134,2,0.08)';this.style.borderColor='rgba(255,134,2,0.15)'">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 16l4-8 4 4 4-8"/></svg>
                <span>Open Profiler</span>
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-left: auto; opacity: 0.5;"><path d="M7 17L17 7"/><path d="M7 7h10v10"/></svg>
            </a>
        </div>
        HTML;
    }

    protected function renderSavingsBlock(array $data): string
    {
        $isBlaze = $data['blazeEnabled'];
        $isCold = $data['isColdRender'];
        $comparison = $data['comparison'];

        if (! $isBlaze) {
            return <<<HTML
            <div style="color: rgba(255,255,255,0.3); font-size: 11px; margin-top: 10px; line-height: 1.5;">
                Click the bubble to enable Blaze<br>
                and see performance savings
            </div>
            HTML;
        }

        if (! $comparison) {
            return <<<HTML
            <div style="color: rgba(255,255,255,0.3); font-size: 11px; margin-top: 10px; line-height: 1.5;">
                Reload in Blade mode first to<br>
                record baseline render times
            </div>
            HTML;
        }

        $warm = $comparison['warm'];
        $cold = $comparison['cold'];

        // Primary = the comparison matching the current render temperature.
        $primary = $isCold ? $cold : $warm;
        $secondary = $isCold ? $warm : $cold;
        $primaryType = $isCold ? 'cold' : 'warm';
        $secondaryType = $isCold ? 'warm' : 'cold';

        if (! $primary) {
            $primary = $secondary;
            $primaryType = $secondaryType;
            $secondary = null;
        }

        if (! $primary) {
            return '';
        }

        // Use live page time for primary so the number matches the big time above.
        $primaryHtml = $this->renderSavingsRow($data['totalTime'], $primary['otherTime'], $primaryType, true);

        $secondaryHtml = '';
        if ($secondary) {
            $secondaryHtml = $this->renderSavingsRow($secondary['currentTime'], $secondary['otherTime'], $secondaryType, false);
        }

        return <<<HTML
        <div style="margin-top: 12px; display: flex; flex-direction: column; gap: 8px; animation: blaze-savings-in 0.3s ease-out 0.1s both;">
            {$primaryHtml}
            {$secondaryHtml}
        </div>
        HTML;
    }

    protected function renderSavingsRow(float $currentTime, float $otherTime, string $type, bool $isPrimary): string
    {
        if ($otherTime <= 0 || $currentTime <= 0) {
            return '';
        }

        $isFaster = $currentTime < $otherTime;
        $multiplier = $isFaster ? ($otherTime / $currentTime) : ($currentTime / $otherTime);
        $multiplierFormatted = round($multiplier, 1) . 'x';

        $color = $isFaster ? '#22c55e' : '#ef4444';
        $rgb = $isFaster ? '34, 197, 94' : '239, 68, 68';
        $word = $isFaster ? 'faster' : 'slower';

        $otherFormatted = $this->formatMs($otherTime);
        $currentFormatted = $this->formatMs($currentTime);

        if ($isPrimary) {
            return <<<HTML
            <div style="background: rgba({$rgb}, 0.06); border: 1px solid rgba({$rgb}, 0.12); border-radius: 4px; padding: 10px 12px;">
                <div style="display: flex; align-items: baseline; gap: 6px;">
                    <span style="color: {$color}; font-weight: 800; font-size: 18px; letter-spacing: -0.5px; line-height: 1;">{$multiplierFormatted}</span>
                    <span style="color: {$color}; font-size: 11px; font-weight: 600;">{$word}</span>
                    <span style="color: rgba(255,255,255,0.3); font-size: 10px; margin-left: auto; align-self: start;">{$type}</span>
                </div>
                <div style="color: rgba(255,255,255,0.3); font-size: 11px; margin-top: 5px; font-family: inherit;">
                    {$otherFormatted} &#8594; {$currentFormatted}
                </div>
            </div>
            HTML;
        }

        return <<<HTML
        <div style="background: rgba({$rgb}, 0.04); border: 1px solid rgba({$rgb}, 0.08); border-radius: 4px; padding: 8px 12px;">
            <div style="display: flex; align-items: baseline; gap: 6px;">
                <span style="color: {$color}; font-weight: 700; font-size: 13px;">{$multiplierFormatted}</span>
                <span style="color: {$color}; font-size: 10px; font-weight: 600;">{$word}</span>
                <span style="color: rgba(255,255,255,0.3); font-size: 9px; margin-left: auto; align-self: start;">{$type}</span>
            </div>
            <div style="color: rgba(255,255,255,0.3); font-size: 10px; margin-top: 2px; font-family: inherit;">
                {$otherFormatted} &#8594; {$currentFormatted}
            </div>
        </div>
        HTML;
    }

    protected function renderComponentDetail(array $data): string
    {
        if ($data['blazeEnabled']) {
            return $this->renderBlazeComponentTable($data['components']);
        }

        return $this->renderBladeComponentTable($data['bladeComponents']);
    }

    protected function renderBlazeComponentTable(array $components): string
    {
        if (empty($components)) {
            return '<div style="color: rgba(255,255,255,0.3); font-size: 11px; padding: 4px 0;">No components rendered.</div>';
        }

        $thStyle = 'padding: 4px 0; font-size: 9px; font-weight: 600; color: rgba(255,255,255,0.3); text-transform: uppercase; letter-spacing: 0.06em; border-bottom: 1px solid #1b1b1b;';
        $tdStyle = 'padding: 3px 0; font-family: inherit; font-size: 11px; white-space: nowrap;';

        $rows = '';
        foreach ($components as $component) {
            $name = htmlspecialchars($component['name']);
            $count = $component['count'];
            $time = $this->formatMs($component['totalTime']);
            $rows .= <<<HTML
            <tr>
                <td style="{$tdStyle} color: rgba(255,255,255,0.7); overflow: hidden; text-overflow: ellipsis; max-width: 140px;">{$name}</td>
                <td style="{$tdStyle} color: rgba(255,255,255,0.3); text-align: right; padding-left: 8px; padding-right: 8px;">{$count}&#215;</td>
                <td style="{$tdStyle} color: rgba(255,255,255,0.5); text-align: right;">{$time}</td>
            </tr>
            HTML;
        }

        return <<<HTML
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="{$thStyle} text-align: left;">Component</th>
                    <th style="{$thStyle} text-align: right; padding-left: 8px; padding-right: 8px;">Count</th>
                    <th style="{$thStyle} text-align: right;">Time</th>
                </tr>
            </thead>
            <tbody>{$rows}</tbody>
        </table>
        HTML;
    }

    protected function renderBladeComponentTable(array $components): string
    {
        if (empty($components)) {
            return '<div style="color: rgba(255,255,255,0.3); font-size: 11px; padding: 4px 0;">No components rendered.</div>';
        }

        $thStyle = 'padding: 4px 0; font-size: 9px; font-weight: 600; color: rgba(255,255,255,0.3); text-transform: uppercase; letter-spacing: 0.06em; border-bottom: 1px solid #1b1b1b;';
        $tdStyle = 'padding: 3px 0; font-family: inherit; font-size: 11px; white-space: nowrap;';

        $rows = '';
        foreach ($components as $component) {
            $name = htmlspecialchars($component['name']);
            $count = $component['count'];
            $rows .= <<<HTML
            <tr>
                <td style="{$tdStyle} color: rgba(255,255,255,0.7); overflow: hidden; text-overflow: ellipsis; max-width: 170px;">{$name}</td>
                <td style="{$tdStyle} color: rgba(255,255,255,0.3); text-align: right;">{$count}&#215;</td>
            </tr>
            HTML;
        }

        return <<<HTML
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="{$thStyle} text-align: left;">Component</th>
                    <th style="{$thStyle} text-align: right;">Count</th>
                </tr>
            </thead>
            <tbody>{$rows}</tbody>
        </table>
        HTML;
    }

    protected function renderBubble(array $data): string
    {
        if ($data['blazeEnabled']) {
            $bgStyle = 'background: #FF8602; animation: blaze-pulse 3s ease-in-out infinite;';
            $icon = $this->boltSvg('#ffffff');
            $tooltip = 'Disable Blaze';
        } else {
            $bgStyle = 'background: #0a0a0a; border: 1px solid #1b1b1b; box-shadow: 0 2px 12px rgba(0, 0, 0, 0.4);';
            $icon = $this->boltSvg('#6366f1');
            $tooltip = 'Enable Blaze';
        }

        return <<<HTML
        <a href="/_blaze/toggle" id="blaze-bubble" style="{$bgStyle}" title="{$tooltip}">
            {$icon}
        </a>
        HTML;
    }

    protected function boltSvg(string $color): string
    {
        return <<<HTML
        <svg width="22" height="22" viewBox="0 0 24 24" fill="{$color}" xmlns="http://www.w3.org/2000/svg">
            <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
        </svg>
        HTML;
    }

    protected function renderScript(): string
    {
        return <<<HTML
        <script>
        (function() {
            var toggle = document.getElementById('blaze-detail-toggle');
            var panel = document.getElementById('blaze-detail-panel');
            var arrow = document.getElementById('blaze-detail-arrow');
            var card = document.getElementById('blaze-card');
            var closeBtn = document.getElementById('blaze-card-close');
            var bubble = document.getElementById('blaze-bubble');
            var hoverTimer = null;

            if (toggle && panel) {
                toggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var isHidden = panel.style.display === 'none';
                    panel.style.display = isHidden ? 'block' : 'none';
                    if (arrow) arrow.style.transform = isHidden ? 'rotate(90deg)' : '';
                });
            }

            if (closeBtn && card) {
                closeBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    card.style.display = 'none';
                });
            }

            if (bubble && card) {
                bubble.addEventListener('mouseenter', function() {
                    if (card.style.display === 'none') {
                        hoverTimer = setTimeout(function() {
                            card.style.display = '';
                            card.style.animation = 'blaze-card-in 0.35s cubic-bezier(0.34, 1.56, 0.64, 1)';
                        }, 400);
                    }
                });

                bubble.addEventListener('mouseleave', function() {
                    if (hoverTimer) {
                        clearTimeout(hoverTimer);
                        hoverTimer = null;
                    }
                });
            }
        })();
        </script>
        HTML;
    }
}
