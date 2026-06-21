<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Laravel;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Vusys\Tetryon\Laravel\Commands\DoctorCommand;
use Vusys\Tetryon\Laravel\Commands\InstallCommand;
use Vusys\Tetryon\Laravel\Commands\ServeCommand;

/**
 * Registers Tetryon inside a Laravel application: merges the package config,
 * makes `config/tetryon.php` publishable, registers the `tetryon:*` commands,
 * and (only in local/testing) exposes the login route that backs `loginAs()`.
 * Auto-discovered via `extra.laravel.providers`. Laravel is never required by
 * the core — this class only loads when the host app provides Illuminate.
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

            $this->commands([
                InstallCommand::class,
                DoctorCommand::class,
                ServeCommand::class,
            ]);
        }

        $this->registerTestingLoginRoute();
    }

    /**
     * A session-aware login route used by {@see BrowserTestCase::loginAs()}.
     * Registered only in local/testing, so it can never authenticate a real
     * request in production.
     */
    private function registerTestingLoginRoute(): void
    {
        if (! $this->app->environment(['local', 'testing'])) {
            return;
        }

        $this->app->make(Router::class)
            ->get('/_tetryon/login/{userId}/{guard?}', static function (string $userId, ?string $guard = null): RedirectResponse {
                auth($guard)->loginUsingId($userId);

                return redirect('/');
            })
            ->middleware('web');
    }

    private function configPath(): string
    {
        return dirname(__DIR__, 2).'/config/tetryon.php';
    }
}
