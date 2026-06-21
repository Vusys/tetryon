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
 * Base for browser tests that serve the `tests/Fixtures/static-site` directory
 * over `php -S` and point the browser at it. Skips when Firefox is absent.
 */
abstract class StaticSiteTestCase extends BrowserTestCase
{
    protected ?StaticSiteServer $server = null;

    protected function setUp(): void
    {
        try {
            new FirefoxBinary()->locate(getenv('TETRYON_FIREFOX_BINARY') ?: null);
        } catch (FirefoxBinaryNotFoundException) {
            self::markTestSkipped('Firefox is not installed; skipping browser test.');
        }

        $this->server = StaticSiteServer::start(__DIR__.'/../Fixtures/static-site');
    }

    protected function tearDown(): void
    {
        $this->server?->stop();
    }

    #[Override]
    protected function browserConfiguration(): Configuration
    {
        $baseUrl = $this->server instanceof StaticSiteServer ? $this->server->baseUrl : 'http://127.0.0.1:8000';

        return new Configuration(baseUrl: $baseUrl);
    }
}
