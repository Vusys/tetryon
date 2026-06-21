<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Firefox\Exception\FirefoxBinaryNotFoundException;
use Vusys\Tetryon\Firefox\FirefoxBinary;
use Vusys\Tetryon\PHPUnit\BrowserTestCase;
use Vusys\Tetryon\Tests\Support\StaticSiteServer;

/**
 * Exercises the v0.2 public surface — BrowserTestCase + the fluent navigation
 * API + text/URL/title assertions — against real Firefox and the static-site
 * fixture. Opt-in (Browser suite); skipped when Firefox is absent.
 */
final class NavigationTest extends BrowserTestCase
{
    private ?StaticSiteServer $server = null;

    protected function setUp(): void
    {
        try {
            new FirefoxBinary()->locate(getenv('TETRYON_FIREFOX_BINARY') ?: null);
        } catch (FirefoxBinaryNotFoundException) {
            self::markTestSkipped('Firefox is not installed; skipping browser navigation test.');
        }

        $this->server = StaticSiteServer::start(__DIR__.'/../Fixtures/static-site');
    }

    protected function tearDown(): void
    {
        $this->server?->stop();
    }

    #[\Override]
    protected function browserConfiguration(): Configuration
    {
        $baseUrl = $this->server instanceof StaticSiteServer ? $this->server->baseUrl : 'http://127.0.0.1:8000';

        return new Configuration(baseUrl: $baseUrl);
    }

    public function test_guest_can_read_pages(): void
    {
        $this->browser()
            ->visit('/index.html')
            ->assertTitleIs('Tetryon Spike')
            ->assertSee('Hello, Tetryon')
            ->assertDontSee('Goodbye, cruel world')
            ->assertPathIs('/index.html');
    }

    public function test_history_navigation(): void
    {
        $browser = $this->browser()->visit('/index.html');

        $browser->visit('/page-two.html')->assertTitleIs('Page Two');
        $browser->back()->assertTitleIs('Tetryon Spike');
        $browser->forward()->assertTitleIs('Page Two');
    }
}
