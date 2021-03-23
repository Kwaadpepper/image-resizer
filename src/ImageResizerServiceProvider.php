<?php

namespace Kwaadpepper\ImageResizer;

use Illuminate\Support\ServiceProvider;

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
            __DIR__ . '/../config' => config_path('image-resizer'),
        ], 'config');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            sprintf('%s/../config/image-resizer.php', __DIR__),
            'image-resizer'
        );
    }
}
