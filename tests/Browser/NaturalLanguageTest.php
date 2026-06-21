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
 * Drives the natural-language layer against real Firefox: the same flow via
 * both the ->step() and ->scenario() forms.
 */
final class NaturalLanguageTest extends BrowserTestCase
{
    private ?StaticSiteServer $server = null;

    #[Override]
    protected function setUp(): void
    {
        try {
            new FirefoxBinary()->locate(getenv('TETRYON_FIREFOX_BINARY') ?: null);
        } catch (FirefoxBinaryNotFoundException) {
            self::markTestSkipped('Firefox is not installed; skipping natural-language test.');
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

    public function test_the_step_form(): void
    {
        $this->browser()
            ->step('I am on "/form.html"')
            ->step('I fill "Name" with "Bryan"')
            ->step('I press "Save"')
            ->step('I should see "Saved: Bryan"');
    }

    public function test_the_scenario_form(): void
    {
        $this->scenario()
            ->given('I am on "/form.html"')
            ->when('I fill "Name" with "Ada"')
            ->and('I press "Save"')
            ->then('I should see "Saved: Ada"');
    }
}
