<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Laravel;

use Illuminate\Support\ServiceProvider;

/**
 * Registers Tetryon inside a Laravel application: merges the package config and
 * (in the console) makes `config/tetryon.php` publishable. Auto-discovered via
 * `extra.laravel.providers`. Laravel is never required by the core — this class
 * only loads when the host app provides Illuminate.
 */
final class TetryonServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom($this->configPath(), 'tetryon');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->configPath() => config_path('tetryon.php'),
            ], 'tetryon-config');
        }
    }

    private function configPath(): string
    {
        return dirname(__DIR__, 2).'/config/tetryon.php';
    }
}
