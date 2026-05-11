<?php

namespace Skaisser\Howl;

use Illuminate\Support\ServiceProvider;

class HowlServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/howl.php', 'howl');

        $this->app->singleton('howl', function ($app) {
            return new Howl($app['config']->get('howl', []));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/howl.php' => config_path('howl.php'),
            ], 'howl-config');
        }
    }
}
