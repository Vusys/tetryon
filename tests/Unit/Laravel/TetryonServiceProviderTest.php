<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Laravel;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Vusys\Tetryon\Laravel\TetryonServiceProvider;

#[CoversClass(TetryonServiceProvider::class)]
final class TetryonServiceProviderTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [TetryonServiceProvider::class];
    }

    public function test_it_merges_the_package_config(): void
    {
        self::assertSame(5000, config('tetryon.timeout.default'));
        self::assertSame(15000, config('tetryon.timeout.navigation'));
        self::assertSame(1280, config('tetryon.viewport.width'));
        self::assertSame(['data-testid', 'data-test', 'data-cy'], config('tetryon.selectors.test_attributes'));
    }

    public function test_it_offers_the_config_for_publishing(): void
    {
        $published = TetryonServiceProvider::pathsToPublish(TetryonServiceProvider::class, 'tetryon-config');

        self::assertNotEmpty($published);
        self::assertContains('tetryon.php', array_map(basename(...), array_values($published)));
    }

    public function test_it_registers_the_artisan_commands(): void
    {
        $app = $this->app;
        self::assertNotNull($app);

        $commands = array_keys($app->make(Kernel::class)->all());

        self::assertContains('tetryon:install', $commands);
        self::assertContains('tetryon:doctor', $commands);
        self::assertContains('tetryon:serve', $commands);
    }

    public function test_it_registers_the_testing_login_route(): void
    {
        $app = $this->app;
        self::assertNotNull($app);

        $uris = array_map(
            static fn (Route $route): string => $route->uri(),
            $app->make(Router::class)->getRoutes()->getRoutes(),
        );

        self::assertContains('_tetryon/login/{userId}/{guard?}', $uris);
    }
}
