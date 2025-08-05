<?php

namespace Livewire\Blaze;

use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;

class BlazeServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/blaze.php', 'blaze'
        );

        $this->app->singleton(BlazeCompiler::class, function ($app) {
            return new BlazeCompiler(
                $app['files'],
                $app['config']->get('view.compiled'),
                $app['config']->get('blaze')
            );
        });

        $this->app->singleton(BlazeOptimizer::class, function ($app) {
            return new BlazeOptimizer(
                $app[BlazeCompiler::class],
                $app['config']->get('blaze')
            );
        });
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/blaze.php' => config_path('blaze.php'),
            ], 'blaze-config');

            $this->commands([
                Commands\OptimizeCommand::class,
                Commands\ClearCommand::class,
                Commands\StatsCommand::class,
            ]);
        }

        $this->app->resolving(BladeCompiler::class, function ($compiler) {
            $this->registerBlazeDirectives($compiler);
        });

        if ($this->app['config']->get('blaze.enabled', true)) {
            $this->app[BlazeOptimizer::class]->boot();
        }
    }

    protected function registerBlazeDirectives(BladeCompiler $compiler)
    {
        $compiler->directive('blazeCache', function ($expression) {
            return "<?php echo app('Livewire\\Blaze\\BlazeCache')->remember($expression, function() { ?>";
        });

        $compiler->directive('endBlazeCache', function () {
            return "<?php }); ?>";
        });

        $compiler->directive('blazeOnce', function ($expression) {
            return "<?php if (! app('Livewire\\Blaze\\BlazeCache')->hasRendered($expression)): ?>";
        });

        $compiler->directive('endBlazeOnce', function () {
            return "<?php endif; ?>";
        });
    }
}