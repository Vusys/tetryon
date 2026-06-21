<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Laravel;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
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
}
