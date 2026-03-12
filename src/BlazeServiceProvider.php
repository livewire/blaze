<?php

namespace Livewire\Blaze;

use Livewire\Blaze\Commands\TraceClearCommand;
use Livewire\Blaze\Commands\TraceListCommand;
use Livewire\Blaze\Commands\TraceShowCommand;
use Livewire\Blaze\Compiler\Profiler;
use Livewire\Blaze\Runtime\BlazeRuntime;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Livewire\Blaze\Memoizer\Memo;

class BlazeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfig();

        $this->app->singleton(BladeService::class);
        $this->app->singleton(BlazeRuntime::class);
        $this->app->singleton(Config::class);
        $this->app->singleton(Debugger::class);
        $this->app->singleton(Profiler::class);
        $this->app->singleton(BlazeManager::class);

        $this->app->singleton(\PhpParser\Parser::class, function () {
            return (new \PhpParser\ParserFactory)->createForNewestSupportedVersion();
        });

        $this->app->alias(BlazeManager::class, Blaze::class);

        $this->app->alias(BlazeManager::class, 'blaze');
        $this->app->alias(BlazeRuntime::class, 'blaze.runtime');
        $this->app->alias(Config::class, 'blaze.config');
        $this->app->alias(Debugger::class, 'blaze.debugger');
    }

    protected function registerConfig(): void
    {
        $config = __DIR__.'/../config/blaze.php';

        $this->publishes([$config => base_path('config/blaze.php')], ['blaze', 'blaze:config']);

        $this->mergeConfigFrom($config, 'blaze');
    }

    public function boot(): void
    {
        $this->registerBlazeDirectives();
        $this->registerViewComposer();
        $this->registerBladeMacros();
        $this->interceptBladeCompilation();
        $this->registerDebuggerMiddleware();
        $this->registerOctaneListener();

        if ($this->app->runningInConsole()) {
            $this->commands([
                TraceClearCommand::class,
                TraceListCommand::class,
                TraceShowCommand::class,
            ]);
        }
    }

    /**
     * Make the BlazeRuntime instance available to Blade views.
     */
    protected function registerViewComposer(): void
    {
        $blaze = $this->app->make(BlazeManager::class);
        $runtime = $this->app->make(BlazeRuntime::class);
        $debugger = $this->app->make(Debugger::class);

        View::composer('*', function (\Illuminate\View\View $view) use ($blaze, $runtime, $debugger) {
            if ($blaze->isDisabled() && ! $blaze->isDebugging()) {
                return;
            }

            if (! str_ends_with($view->getPath(), '.blade.php')) {
                return;
            }

            if ($blaze->viewContainsExpiredFrontMatter($view)) {
                $view->getEngine()->getCompiler()->compile($view->getPath());
            }

            if ($blaze->isDebugging()) {
                $debugger->injectRenderTimer($view);

                if ($blaze->isDisabled()) {
                    $name = $view->name();

                    if (str_contains($name, '::')) {
                        $name = substr($name, strpos($name, '::') + 2);
                    }

                    $debugger->incrementBladeComponents($name);
                }
            }

            $view->with('__blaze', $runtime);
        });
    }

    /**
     * Register @blaze, @unblaze, and @endunblaze Blade directives.
     */
    protected function registerBlazeDirectives(): void
    {
        Blade::directive('blaze', function () {
            return '';
        });

        Blade::directive('unblaze', function ($expression) {
            return ''
                . '<'.'?php $__getScope = fn($scope = []) => $scope; ?>'
                . '<'.'?php if (isset($scope)) $__scope = $scope; ?>'
                . '<'.'?php $scope = $__getScope('.$expression.'); ?>';
        });

        Blade::directive('endunblaze', function () {
            return '<'.'?php if (isset($__scope)) { $scope = $__scope; unset($__scope); } ?>';
        });
    }

    /**
     * Register view factory macros for consumable component data (@aware support).
     */
    protected function registerBladeMacros(): void
    {
        View::macro('pushConsumableComponentData', function ($data) {
            /** @var \Illuminate\View\Factory $this */
            $this->componentStack[] = new \Illuminate\Support\HtmlString('');
            $this->componentData[$this->currentComponent()] = $data;
        });

        View::macro('popConsumableComponentData', function () {
            /** @var \Illuminate\View\Factory $this */
            array_pop($this->componentStack);
        });
    }

    /**
     * Hook into Blade's pre-compilation phase to run the Blaze pipeline.
     */
    protected function interceptBladeCompilation(): void
    {
        $blade = $this->app->make(BladeService::class);
        $blaze = $this->app->make(BlazeManager::class);

        $blade->earliestPreCompilationHook(function ($input, $path) use ($blade, $blaze) {
            if ($blade->containsLaravelExceptionView($input)) {
                return $input;
            }

            if ($blaze->isDisabled()) {
                if ($blaze->isDebugging()) {
                    return $blaze->compileForDebug($input, $path);
                }

                return $input;
            }

            return $blaze->collectAndAppendFrontMatter($input, function ($input) use ($path, $blaze) {
                return $blaze->compile($input, $path);
            });
        });
    }

    /**
     * Register the Debugger middleware.
     */
    protected function registerDebuggerMiddleware(): void
    {
        $this->app->booted(function () {
            if (Blaze::isDebugging()) {
                DebuggerMiddleware::register();
            }
        });
    }

    /**
     * Reset Blaze state between Octane requests.
     */
    protected function registerOctaneListener(): void
    {
        Event::listen(\Laravel\Octane\Events\RequestReceived::class, function ($event) {
            $app = $event->sandbox;
            
            $runtime = $app->make(BlazeRuntime::class);
            $manager = $app->make(BlazeManager::class);
            $debugger = $app->make(Debugger::class);

            $runtime->setApplication($app);

            $runtime->flushState();
            $manager->flushState();
            $debugger->flushState();

            Unblaze::flushState();
            Memo::flushState();
        });
    }
}
