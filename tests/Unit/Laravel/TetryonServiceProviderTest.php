<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Laravel;

use Illuminate\Foundation\Application;
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
}
