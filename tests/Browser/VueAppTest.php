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
 * Drives a real Vue 3 single-page app (served as static files, no build step)
 * to prove Tetryon handles client-side rendering — reactive updates, async
 * data, form validation, and client-side view switching — with no manual waits.
 */
final class VueAppTest extends BrowserTestCase
{
    private ?StaticSiteServer $server = null;

    #[Override]
    protected function setUp(): void
    {
        try {
            new FirefoxBinary()->locate(getenv('TETRYON_FIREFOX_BINARY') ?: null);
        } catch (FirefoxBinaryNotFoundException) {
            self::markTestSkipped('Firefox is not installed; skipping Vue app test.');
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

    public function test_reactivity_async_and_form_validation(): void
    {
        $this->browser()
            ->visit('/vue-app/index.html')
            // reactive counter + conditional rendering
            ->assertDontSee('Clicked')
            ->press('Increment')
            ->assertSee('Clicked 1 times')
            // async-loaded list (renders ~400ms later — auto-waited)
            ->press('Load users')
            ->assertSee('Ada Lovelace')
            ->assertSee('Grace Hopper')
            // reactive form validation as the model changes
            ->fill('Email', 'bad')
            ->assertSee('Invalid email')
            ->fill('Email', 'ada@example.com')
            ->assertDontSee('Invalid email')
            ->press('Sign up')
            ->assertSee('Welcome, ada@example.com');
    }

    public function test_client_side_view_switching(): void
    {
        $this->browser()
            ->visit('/vue-app/index.html')
            ->assertSee('Increment')
            ->click('About')
            ->assertSee('About this app')
            ->assertDontSee('Increment')   // home view unmounted, no page reload
            ->click('Home')
            ->assertSee('Increment');
    }
}
