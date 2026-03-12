<?php

namespace Livewire\Blaze;

use Closure;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DebuggerMiddleware
{
    /**
     * Register the debug bar routes and middleware.
     */
    public static function register(): void
    {
        Route::get('/_blaze/trace', function (Request $request) {
            $store = app('blaze.debugger')->store;

            $trace = $request->query('id')
                ? $store->getTrace($request->query('id'))
                : $store->getLatestTrace();

            return response()->json($trace ?? ['entries' => [], 'url' => null]);
        })->middleware('web');

        Route::get('/_blaze/traces', function () {
            return response()->json(app('blaze.debugger')->store->listTraces());
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
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $url = '/' . ltrim($request->path(), '/');

        // Skip internal debug bar routes and Livewire requests.
        if (str_starts_with($url, '/_blaze/') || $request->hasHeader('X-Livewire')) {
            return $next($request);
        }

        $isBlaze = app('blaze')->isEnabled();

        $debugger = app('blaze.debugger');
        $debugger->setBlazeEnabled($isBlaze);

        /** @var SymfonyResponse $response */
        $response = $next($request);

        if ($response->getStatusCode() === 200) {
            $this->storeProfilerTrace($url, $debugger, $isBlaze);
            $this->injectDebugger($response, $debugger);
        }

        return $response;
    }

    /**
     * Store profiler trace data for the profiler page to consume.
     */
    protected function storeProfilerTrace(string $url, Debugger $debugger, bool $isBlaze): void
    {
        $trace = $debugger->getTraceData();

        if (empty($trace['entries'])) {
            return;
        }

        $debugger->store->storeTrace([
            'url'          => $url,
            'mode'         => $isBlaze ? 'blaze' : 'blade',
            'timestamp'    => now()->toIso8601String(),
            'renderTime'   => $trace['totalTime'],
            'entries'      => $trace['entries'],
            'components'   => $trace['components'],
            'debugBar'     => $debugger->getDebugBarData(),
        ]);
    }

    /**
     * Inject the debug bar HTML before the closing </body> tag.
     *
     * Based on https://github.com/fruitcake/laravel-debugbar/blob/master/src/LaravelDebugbar.php
     */
    protected function injectDebugger(SymfonyResponse $response, Debugger $debugger): void
    {
        $content = $response->getContent();

        $widget = "<!-- Blaze Widget -->\n" . $debugger->render();

        // Try to put the widget at the end, directly before the </body>
        $pos = strripos($content, '</body>');
        if (false !== $pos) {
            $content = substr($content, 0, $pos) . $widget . substr($content, $pos);
        } else {
            $content = $content . $widget;
        }

        $original = null;
        if ($response instanceof Response && $response->getOriginalContent()) {
            $original = $response->getOriginalContent();
        }

        // Update the new content and reset the content length
        $response->setContent($content);
        $response->headers->remove('Content-Length');

        // Restore original response (e.g. the View or Ajax data)
        if ($response instanceof Response && $original) {
            $response->original = $original;
        }
    }
}
