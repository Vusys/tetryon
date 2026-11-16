<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Laravel;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Vusys\Tetryon\Core\Server\ManagedServer;
use Vusys\Tetryon\Laravel\LaravelConfiguration;
use Vusys\Tetryon\Laravel\TetryonServiceProvider;

#[CoversClass(LaravelConfiguration::class)]
final class LaravelConfigurationTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [TetryonServiceProvider::class];
    }

    public function test_it_builds_a_configuration_from_laravel_config(): void
    {
        config([
            'tetryon.base_url' => 'http://localhost:9999',
            'tetryon.headless' => false,
            'tetryon.timeout.navigation' => 30000,
            'tetryon.viewport.width' => 1920,
            'tetryon.selectors.test_attributes' => ['data-qa'],
        ]);

        $configuration = LaravelConfiguration::resolve();

        self::assertSame('http://localhost:9999', $configuration->baseUrl);
        self::assertFalse($configuration->headless);
        self::assertSame(30000, $configuration->timeouts->navigation);
        self::assertSame(1920, $configuration->viewport->width);
        self::assertSame(['data-qa'], $configuration->selectorTestAttributes);
    }

    public function test_it_falls_back_to_defaults_for_missing_or_wrong_types(): void
    {
        config(['tetryon.base_url' => 123, 'tetryon.headless' => 'nope']);

        $configuration = LaravelConfiguration::resolve();

        self::assertSame('http://127.0.0.1:8000', $configuration->baseUrl);
        self::assertTrue($configuration->headless);
    }

    public function test_a_managed_server_supplies_the_base_url(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($socket);
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);
        $port = (int) substr($name, (int) strrpos($name, ':') + 1);

        config([
            'tetryon.base_url' => 'http://ignored.test',
            'tetryon.server.managed' => true,
            'tetryon.server.command' => PHP_BINARY.' -S {host}:{port} -t '.__DIR__.'/../../Fixtures/server-env',
            'tetryon.server.base_port' => $port,
            'tetryon.server.env' => ['TETRYON_PROBE' => 'laravel'],
        ]);

        try {
            $configuration = LaravelConfiguration::resolve();

            self::assertSame('http://127.0.0.1:'.$port, $configuration->baseUrl);
            self::assertSame('laravel', file_get_contents($configuration->baseUrl));
        } finally {
            ManagedServer::stopRunning();
        }
    }
}
