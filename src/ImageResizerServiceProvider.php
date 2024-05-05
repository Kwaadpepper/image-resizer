<?php

namespace Kwaadpepper\ImageResizer;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Kwaadpepper\ImageResizer\Console\AutoCleanCacheCommand;

class ImageResizerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        require_once sprintf('%s/helpers.php', __DIR__);

        $this->publishes([
            __DIR__ . '/../config' => config_path(),
        ], 'image-resizer-config');

        // * Register the command if we are using the application via the CLI .
        if ($this->app->runningInConsole()) {
            $this->commands([
                AutoCleanCacheCommand::class,
            ]);
        }

        // * Cron Run cleaner every half hour
        $this->app->booted(function () {
            if ($this->app->environment('production')) {
                /** @var \Illuminate\Console\Scheduling\Schedule */
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('image-resizer:clean-cache')->everyThirtyMinutes();
            }
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            sprintf('%s/../config/image-resizer.php', __DIR__),
            'image-resizer'
        );
    }
}
