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
 * Exercises the form verbs and visibility assertions against real Firefox:
 * fill/check/select by label, value/visibility assertions, and the resulting
 * DOM after a submit.
 */
final class FormTest extends BrowserTestCase
{
    private ?StaticSiteServer $server = null;

    #[Override]
    protected function setUp(): void
    {
        try {
            new FirefoxBinary()->locate(getenv('TETRYON_FIREFOX_BINARY') ?: null);
        } catch (FirefoxBinaryNotFoundException) {
            self::markTestSkipped('Firefox is not installed; skipping form test.');
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

    public function test_fill_check_select_then_submit(): void
    {
        $this->browser()
            ->visit('/signup.html')
            ->fill('Email', 'bryan@example.com')      // resolved via its <label>
            ->check('Remember me')                    // checkbox wrapped in a label
            ->select('Country', 'uk')                 // <select> resolved via its <label>
            ->assertValue('Email', 'bryan@example.com')
            ->press('Submit')
            ->assertSee('email=bryan@example.com;remember=true;country=uk');
    }

    public function test_visibility_assertions(): void
    {
        $this->browser()
            ->visit('/signup.html')
            ->assertVisible('Create your account')
            ->assertMissing('hidden text')            // display:none element
            ->assertMissing('Nothing here at all')    // never present
            ->assertTextNear('Status:', 'Active');
    }
}
