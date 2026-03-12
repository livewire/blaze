<?php

namespace Livewire\Blaze;

use Closure;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

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

        /** @var Response $response */
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
