<?php

namespace IhorRadchenko\LaravelIdeHelperMacros;

use Barryvdh\Reflection\DocBlock\Tag;
use Illuminate\Support\ServiceProvider;
use IhorRadchenko\LaravelIdeHelperMacros\Console\IdeHelperMacros;

class IdeHelperMacrosServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->commands([
            IdeHelperMacros::class,
        ]);

        $this->publishes([
            __DIR__ . '/../config/ide-helper-macros.php' => config_path('ide-helper-macros.php')
        ]);
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ide-helper-macros.php', 'ide-helper-macros');

        Tag::registerTagHandler('package', PackageTag::class);
    }
}