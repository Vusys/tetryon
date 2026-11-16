<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

use Override;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Firefox\Exception\FirefoxBinaryNotFoundException;
use Vusys\Tetryon\Firefox\FirefoxBinary;
use Vusys\Tetryon\PHPUnit\BrowserTestCase;
use Vusys\Tetryon\Tests\Support\StaticSiteServer;

/**
 * Like StaticSiteTestCase, but one server for the whole class: a reused
 * browser keeps the first test's base URL, so the origin must not move.
 */
abstract class ReusedBrowserTestCase extends BrowserTestCase
{
    protected static ?StaticSiteServer $classServer = null;

    /** @var array<class-string, int> */
    protected static array $authentications = [];

    /** @var array<class-string, list<int>> */
    protected static array $drivers = [];

    public static function setUpBeforeClass(): void
    {
        try {
            new FirefoxBinary()->locate(getenv('TETRYON_FIREFOX_BINARY') ?: null);
        } catch (FirefoxBinaryNotFoundException) {
            self::markTestSkipped('Firefox is not installed; skipping browser test.');
        }

        static::$classServer = StaticSiteServer::start(__DIR__.'/../Fixtures/static-site');
    }

    public static function tearDownAfterClass(): void
    {
        static::$classServer?->stop();
        static::$classServer = null;
    }

    #[Override]
    protected function browserConfiguration(): Configuration
    {
        $baseUrl = static::$classServer instanceof StaticSiteServer ? static::$classServer->baseUrl : 'http://127.0.0.1:8000';

        return new Configuration(baseUrl: $baseUrl);
    }

    protected function countAuthentication(): void
    {
        static::$authentications[static::class] = (static::$authentications[static::class] ?? 0) + 1;
    }

    protected function noteDriver(): void
    {
        static::$drivers[static::class][] = spl_object_id($this->driver());
    }
}
