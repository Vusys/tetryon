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
 * Drives the selector engine against real Firefox: clicking/pressing a target
 * by human label (visible text, explicit test attribute) resolves to the right
 * element and fires real events.
 */
final class InteractionTest extends BrowserTestCase
{
    private ?StaticSiteServer $server = null;

    #[Override]
    protected function setUp(): void
    {
        try {
            new FirefoxBinary()->locate(getenv('TETRYON_FIREFOX_BINARY') ?: null);
        } catch (FirefoxBinaryNotFoundException) {
            self::markTestSkipped('Firefox is not installed; skipping interaction test.');
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

    public function test_press_resolves_a_button_by_its_visible_text(): void
    {
        $this->browser()
            ->visit('/form.html')
            ->press('Save')
            ->assertSee('Saved:');
    }

    public function test_click_resolves_an_explicit_test_attribute(): void
    {
        $this->browser()
            ->visit('/form.html')
            ->click('@save')
            ->assertSee('Saved:');
    }
}
