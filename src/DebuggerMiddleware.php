<?php

namespace Livewire\Blaze;

use Closure;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class DebuggerMiddleware
{
    /**
     * Register the debug bar routes and middleware.
     */
    public static function register(): void
    {
        Route::get('/_blaze/trace', function () {
            return response()->json(Cache::get('blaze_profiler_trace', ['entries' => [], 'url' => null]));
        })->middleware('web');

        Route::get('/_blaze/profiler', function () {
            $html = file_get_contents(__DIR__.'/Profiler/profiler.html');
            return response($html)->header('Content-Type', 'text/html');
        })->middleware('web');

        app(Kernel::class)->pushMiddleware(static::class);
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $url = '/' . ltrim($request->path(), '/');

        // Skip internal debug bar routes and Livewire requests.
        if (str_starts_with($url, '/_blaze/') || $request->hasHeader('X-Livewire')) {
            return $next($request);
        }

        $isBlaze = app('blaze')->isEnabled();

        $debugger = app('blaze.debugger');
        $debugger->setBlazeEnabled($isBlaze);

        $response = $next($request);

        if ($response->status() === 200) {
            $this->recordAndCompare($url, $debugger, $isBlaze);
            $this->storeProfilerTrace($url, $debugger, $isBlaze);
            $this->injectDebugger($response, $debugger);
        }

        return $response;
    }

    /**
     * Record the render time for this page and build comparison data
     * against the other mode's stored times (cold vs cold, warm vs warm).
     */
    protected function recordAndCompare(string $url, Debugger $debugger, bool $isBlaze): void
    {
        $mode = $isBlaze ? 'blaze' : 'blade';
        $renderTime = $debugger->getPageRenderTime();

        // Cold = first render of this URL in this mode since views were last cleared.
        $seenKey = "{$mode}:{$url}";
        $seenPages = Cache::get('blaze_seen_pages', []);
        $isCold = ! isset($seenPages[$seenKey]);

        // Store the render time keyed by mode and temperature.
        $times = Cache::get('blaze_page_times', []);
        $times[$url] ??= [];

        $timeKey = $isCold ? "{$mode}_cold" : "{$mode}_warm";
        $times[$url][$timeKey] = $renderTime;

        $seenPages[$seenKey] = true;

        Cache::put('blaze_page_times', $times);
        Cache::put('blaze_seen_pages', $seenPages);

        $debugger->setIsColdRender($isCold);

        // Build comparison: only compare like-for-like (cold↔cold, warm↔warm).
        $pageData = $times[$url];
        $otherMode = $isBlaze ? 'blade' : 'blaze';

        $warmComparison = null;
        $coldComparison = null;

        if (isset($pageData["{$mode}_warm"], $pageData["{$otherMode}_warm"])) {
            $warmComparison = [
                'currentTime' => $pageData["{$mode}_warm"],
                'otherTime' => $pageData["{$otherMode}_warm"],
            ];
        }

        if (isset($pageData["{$mode}_cold"], $pageData["{$otherMode}_cold"])) {
            $coldComparison = [
                'currentTime' => $pageData["{$mode}_cold"],
                'otherTime' => $pageData["{$otherMode}_cold"],
            ];
        }

        $debugger->setComparison(
            ($warmComparison || $coldComparison)
                ? ['otherMode' => $otherMode, 'warm' => $warmComparison, 'cold' => $coldComparison]
                : null
        );
    }

    /**
     * Store profiler trace data in cache for the profiler page to consume.
     */
    protected function storeProfilerTrace(string $url, Debugger $debugger, bool $isBlaze): void
    {
        $trace = $debugger->getTraceData();

        if (empty($trace['entries'])) {
            return;
        }

        Cache::put('blaze_profiler_trace', [
            'url'        => $url,
            'mode'       => $isBlaze ? 'blaze' : 'blade',
            'timestamp'  => now()->toIso8601String(),
            'renderTime' => $trace['totalTime'],
            'entries'    => $trace['entries'],
            'memoHits'   => $trace['memoHits'],
            'memoHitNames' => $trace['memoHitNames'],
            'debugBar'   => $debugger->getDebugBarData(),
        ], 300); // 5 minutes
    }

    /**
     * Inject the debug bar HTML after the opening <body> tag.
     */
    protected function injectDebugger(Response $response, Debugger $debugger): void
    {
        if (! method_exists($response, 'getContent')) {
            return;
        }

        $content = $response->getContent();

        if (! $content || ! preg_match('/<body[^>]*>/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return;
        }

        $insertPos = $matches[0][1] + strlen($matches[0][0]);

        $response->setContent(
            substr($content, 0, $insertPos) . "\n" . $debugger->render() . substr($content, $insertPos)
        );
    }
}
