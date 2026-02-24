<?php

namespace Livewire\Blaze;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class DebuggerMiddleware
{
    /**
     * Register the debug bar routes and middleware.
     */
    public static function register(): void
    {
        Route::get('/_blaze/toggle', function (Request $request) {
            Cache::put('blaze_enabled', ! Cache::get('blaze_enabled', false));
            Cache::forget('blaze_seen_pages');

            Artisan::call('view:clear');

            return redirect()->to(url()->previous('/'));
        })->middleware('web');

        Route::get('/_blaze/trace', function () {
            return response()->json(Cache::get('blaze_profiler_trace', ['entries' => [], 'url' => null]));
        })->middleware('web');

        Route::get('/_blaze/profiler', function () {
            $html = file_get_contents(__DIR__.'/Profiler/profiler.html');
            return response($html)->header('Content-Type', 'text/html');
        })->middleware('web');

        app('router')->pushMiddlewareToGroup('web', static::class);
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $url = '/' . ltrim($request->path(), '/');

        // Skip internal debug bar routes.
        if (str_starts_with($url, '/_blaze/')) {
            return $next($request);
        }

        $isBlaze = (bool) Cache::get('blaze_enabled', false);

        $debugger = app('blaze.runtime')->debugger;
        $debugger->setBlazeEnabled($isBlaze);

        if ($isBlaze) {
            app('blaze')->enable();
        } else {
            app('blaze')->disable();
        }

        // Inject render timer into the first view's compiled file so we
        // measure only actual view rendering, not the full request pipeline.
        // The composing event fires before the compiled file is included,
        // so modifying it here is safe.
        $timerInjected = false;

        Event::listen('composing:*', function (string $event, array $data) use ($debugger, $isBlaze, &$timerInjected) {
            if (! $timerInjected) {
                $timerInjected = $this->injectRenderTimer($data[0]);
            }

            if (! $isBlaze) {
                $name = str_replace('composing: ', '', $event);

                if (str_contains($name, '::')) {
                    $name = substr($name, strpos($name, '::') + 2);
                }

                $debugger->incrementBladeComponents($name);
            }
        });

        $response = $next($request);

        $this->recordAndCompare($url, $debugger, $isBlaze);
        $this->storeProfilerTrace($url, $debugger, $isBlaze);
        $this->injectDebugger($response, $debugger);

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
     * Inject start/stop timer calls into the compiled file of the first
     * view being rendered. This ensures we measure only view rendering
     * time, not the full request lifecycle.
     */
    protected function injectRenderTimer(\Illuminate\View\View $view): bool
    {
        $compiler = app('blade.compiler');
        $path = $view->getPath();

        // Some views (e.g. Livewire virtual views) may not have a real path.
        if (! $path || ! file_exists($path)) {
            return false;
        }

        // Ensure the view is compiled.
        if ($compiler->isExpired($path)) {
            $compiler->compile($path);
        }

        $compiledPath = $compiler->getCompiledPath($path);

        if (! file_exists($compiledPath)) {
            return false;
        }

        $compiled = file_get_contents($compiledPath);

        // Record which view was wrapped with the render timer.
        app('blaze.runtime')->debugger->setTimerView($this->resolveViewName($view));

        // Already injected (persisted from a previous request).
        if (str_contains($compiled, '__blaze_timer')) {
            return true;
        }

        $start = '<?php ($__blaze ?? app(\'blaze.runtime\'))->debugger->startRenderTimer(); /* __blaze_timer */ ?>';
        $stop = '<?php ($__blaze ?? app(\'blaze.runtime\'))->debugger->stopRenderTimer(); ?>';

        file_put_contents($compiledPath, $start . $compiled . $stop);

        return true;
    }

    /**
     * Resolve a human-readable name for the view being timed.
     *
     * For Livewire SFCs the view path points to an extracted blade file
     * (e.g. storage/.../livewire/views/6ea59dbe.blade.php) which isn't
     * meaningful. In that case we pull the component name from Livewire's
     * shared view data instead.
     */
    protected function resolveViewName(\Illuminate\View\View $view): string
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
