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
 * Proves auto-waiting against real Firefox: content that appears ~400ms after
 * an action is asserted/clicked with no manual sleep.
 */
final class AutoWaitTest extends BrowserTestCase
{
    private ?StaticSiteServer $server = null;

    #[Override]
    protected function setUp(): void
    {
        try {
            new FirefoxBinary()->locate(getenv('TETRYON_FIREFOX_BINARY') ?: null);
        } catch (FirefoxBinaryNotFoundException) {
            self::markTestSkipped('Firefox is not installed; skipping auto-wait test.');
        }

        $this->server = StaticSiteServer::start(__DIR__.'/../Fixtures/static-site');
    }

    #[Override]
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

    public function test_assertions_wait_for_delayed_content(): void
    {
        $this->browser()
            ->visit('/delayed.html')
            ->press('Go')
            ->assertSee('Loaded!')          // text set ~400ms later
            ->assertVisible('Now visible');  // display toggled ~400ms later
    }

    public function test_actions_wait_for_a_late_appearing_element(): void
    {
        $this->browser()
            ->visit('/delayed.html')
            ->press('Go')
            ->click('Second action')        // button created ~400ms later
            ->assertSee('clicked second');
    }

    public function test_explicit_wait_for_text(): void
    {
        $this->browser()
            ->visit('/delayed.html')
            ->press('Go')
            ->waitForText('Loaded!')
            ->assertSee('Now visible');
    }
}
